<?php
namespace EveBlueprint\DatabaseVendor;

class Mysql
{

    private $schemaName;
    private $dbh;

    public function __construct(\PDO $dbh, $schemaName = 'eve')
    {
        $query=$dbh->query("select count(*) from $schemaName.invBlueprintTypes");
        
        if (!($query)) {
            throw new Exception("$schemaName does not contain invBlueprintTypes");
        }
        $query->closeCursor();
        $this->schemaName=$schemaName;
        $this->dbh=$dbh;

    }

    public function baseMaterials($typeid)
    {
        $sql=<<<EOS
        select typeid,name,greatest(0,sum(quantity)) quantity from 
        (select invTypes.typeid typeid,invTypes.typeName name,quantity  from 
        $this->schemaName.invTypes
        join $this->schemaName.invTypeMaterials on (invTypeMaterials.materialTypeID=invTypes.typeID)
        where invTypeMaterials.TypeID=:typeid
        union 
        select invTypes.typeid typeid,invTypes.typeName name,invTypeMaterials.quantity*r.quantity*-1 quantity 
        from $this->schemaName.invTypes
        join $this->schemaName.invTypeMaterials on (invTypeMaterials.materialTypeID=invTypes.typeID)
        join $this->schemaName.ramTypeRequirements r on (invTypeMaterials.TypeID =r.requiredTypeID)
        join $this->schemaName.invBlueprintTypes bt on (r.typeID = bt.blueprintTypeID)
        where 
        r.activityID = 1 
        and bt.productTypeID=:typeid 
        and r.recycle=1) t group by typeid,name
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $basematerials=array();
        while ($row = $stmt->fetchObject()) {
            if ($row->quantity>0) {
                $basematerials[]=array("typeid"=>(int)$row->typeid,"name"=>$row->name,"quantity"=>(int)$row->quantity);
            }
        }
        return $basematerials;
    }

    public function extraMaterials($typeid)
    {
        $sql=<<<EOS
        select name,typeid,max(quantity) quantity ,max(damage) damage ,max(base) base from (
        SELECT t.typeName name, r.quantity quantity, r.damagePerJob damage,t.typeID typeid ,0 base
        FROM $this->schemaName.ramTypeRequirements r
        join $this->schemaName.invTypes t on (r.requiredTypeID = t.typeID)
        join $this->schemaName.invBlueprintTypes bt on ( r.typeID = bt.blueprintTypeID)
        join $this->schemaName.invGroups g on (t.groupID = g.groupID) 
        where 
        r.activityID = 1 
        and bt.productTypeID=:typeid 
        and g.categoryID != 16
        union
        select typename name,0 quantity,0 damage,invTypes.typeid,1 base
        from $this->schemaName.invTypeMaterials
        join $this->schemaName.invTypes on (invTypeMaterials.materialtypeid=invTypes.typeid)
        where invTypeMaterials.typeid=:typeid
        ) t group by name,typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $extramaterials=array();
        while ($row = $stmt->fetchObject()) {
            if ($row->quantity>0) {
                $extramaterials[]=array(
                    "typeid"=>(int)$row->typeid,
                    "name"=>$row->name,
                    "quantity"=>(int)$row->quantity,
                    "damage"=>(float)$row->damage,
                    "baseMaterial"=>(int)$row->base
                );
            }
        }
        return $extramaterials;
    }

    public function blueprintSkills($typeid)
    {
        $sql=<<<EOS
        SELECT t.typeName name, t.typeid,r.quantity level,activityid
        FROM $this->schemaName.ramTypeRequirements r
        join $this->schemaName.invTypes t on (r.requiredTypeID = t.typeID)
        join $this->schemaName.invBlueprintTypes bt on (r.typeID = bt.blueprintTypeID)
        join $this->schemaName.invGroups g on (t.groupID = g.groupID)
        where
        bt.productTypeID=:typeid 
        and g.categoryID = 16 
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $skills=array();
        while ($row = $stmt->fetchObject()) {
            $skills[(int)$row->activityid][]=array(
                "typeid"=>(int)$row->typeid,
                "name"=>$row->name,
                "level"=>(int)$row->level
            );
        }
        $sql=<<<EOS
        select metagroupid,parentTypeID from $this->schemaName.invMetaTypes where typeid=:typeid
EOS;
        $stmt=$this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $metalevel=0;
        $parent=0;
        while ($row = $stmt->fetchObject()) {
            $metalevel=$row->metagroupid;
            $parent=$row->parentTypeID;
        }
        if ($metalevel==2) {
            $inventionskills=$this->blueprintSkills($parent);
            if (isset($inventionskills[8])) {
                $skills[8]=$inventionskills[8];
            }
        } else {
            $inventionskillssql=<<<EOS
            SELECT t.typeid,attributeid,coalesce(valueFloat,valueInt) value,
            t2.typename
            FROM $this->schemaName.ramTypeRequirements r
            join $this->schemaName.invTypes t on (r.requiredTypeID = t.typeID)
            join $this->schemaName.invBlueprintTypes bt on (r.typeID = bt.blueprintTypeID)
            join $this->schemaName.invGroups g on (t.groupID = g.groupID)
            join $this->schemaName.dgmTypeAttributes on (t.typeid=dgmTypeAttributes.typeid)
            left join $this->schemaName.invTypes t2 on (coalesce(valueFloat,valueInt) = t2.typeid
            and dgmTypeAttributes.attributeid in (182,183,184,1285,1289,1290))
            where         
            bt.productTypeID=:typeid 
            and g.categoryid!=16 
            and activityid=8 
            and dgmTypeAttributes.attributeid in (182,183,184,277,278,279,1285,1286,1289,1288,1289,1290)
EOS;
            $stmt=$this->dbh->prepare($inventionskillssql);
            $stmt->execute(array(":typeid"=>$typeid));
            $holder=array();
            while ($row = $stmt->fetchObject()) {
                switch ($row->attributeid) {
                    case 182:
                    case 183:
                    case 184:
                    case 1285:
                    case 1289:
                    case 1290:
                        $holder[$row->typeid][$row->attributeid]["skill"]=array(
                            "typeid"=>(int)$row->value,
                            "name"=>$row->typename
                        );
                        break;
                    case 277:
                        $holder[$row->typeid][182]["level"]=(int)$row->value;
                        break;
                    case 278:
                        $holder[$row->typeid][183]["level"]=(int)$row->value;
                        break;
                    case 279:
                        $holder[$row->typeid][184]["level"]=(int)$row->value;
                        break;
                    case 1286:
                        $holder[$row->typeid][1285]["level"]=(int)$row->value;
                        break;
                    case 1287:
                        $holder[$row->typeid][1289]["level"]=(int)$row->value;
                        break;
                    case 1288:
                        $holder[$row->typeid][1290]["level"]=(int)$row->value;
                        break;
                }
            }
            foreach ($holder as $key => $value) {
                foreach ($value as $attributeid => $details) {
                    $skills[8][]=
                    array(
                        "typeid"=>(int)$details["skill"]["typeid"],
                        "name"=>$details["skill"]["name"],
                        "level"=>(int)$details["level"]
                    );
                }
            }
        }
        return $skills;
    }
    
    public function activityMaterials($typeid)
    {
        $sql=<<<EOS
        SELECT t.typeName name, t.typeid,r.quantity quantity,r.damagePerJob damage,activityid,t.groupid
        FROM $this->schemaName.ramTypeRequirements r
        join $this->schemaName.invTypes t on (r.requiredTypeID = t.typeID)
        join $this->schemaName.invBlueprintTypes bt on (r.typeID = bt.blueprintTypeID)
        join $this->schemaName.invGroups g on (t.groupID = g.groupID)
        where
        bt.productTypeID=:typeid 
        and g.categoryID != 16 
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $materials=array();
        while ($row = $stmt->fetchObject()) {
            if (716==$row->groupid) {
                $damage=0;
            } else {
                $damage=$row->damage;
            }
            $materials[$row->activityid][]=array(
                "typeid"=>(int)$row->typeid,
                "name"=>$row->name,
                "quantity"=>(int)$row->quantity,
                "damage"=>(float)$damage,
            );
        }
        $sql=<<<EOS
        select metagroupid,parentTypeID from $this->schemaName.invMetaTypes where typeid=:typeid
EOS;
        $stmt=$this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $metalevel=0;
        $parent=0;
        while ($row = $stmt->fetchObject()) {
            $metalevel=(int)$row->metagroupid;
            $parent=(int)$row->parentTypeID;
        }
        if ($metalevel==2) {
            $inventionmaterials=$this->activityMaterials($parent);
            if (isset($inventionmaterials[8])) {
                $materials[8]=$inventionmaterials[8];
            }
        }
        return $materials;
    }

    public function blueprintDetails($typeid)
    {
        $sql=<<<EOS
        select blueprintTypeID,techLevel,productionTime,wasteFactor,productivityModifier,researchProductivityTime,
        researchMaterialTime,researchCopyTime,researchTechTime,materialModifier,maxProductionLimit 
        FROM $this->schemaName.invBlueprintTypes 
        where productTypeID=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $details=array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $details=$row;
        }
        
        if ($details["techLevel"]==2) {
            $sql=<<<EOS
            select researchTechTime,parenttypeid,invBlueprintTypes.blueprinttypeid
            FROM $this->schemaName.invBlueprintTypes
            JOIN $this->schemaName.invMetaTypes on (invMetaTypes.parenttypeid=invBlueprintTypes.producttypeid)
            where
            invMetaTypes.typeid=:typeid
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            while ($row = $stmt->fetchObject()) {
                $details["researchTechTime"]=$row->researchTechTime;
                $details["t1Product"]=$row->parenttypeid;
                $details["t1BlueprintTypeID"]=$row->blueprinttypeid;
            }
        }
        return $details;
    }

    public function metaVersions($typeid)
    {
        $sql=<<<EOS
        SELECT invMetaTypes.typeid,typename,coalesce(valuefloat,valueint) level 
        FROM $this->schemaName.invMetaTypes
        JOIN $this->schemaName.invTypes on invMetaTypes.typeid=invTypes.typeid
        JOIN $this->schemaName.dgmTypeAttributes on (dgmTypeAttributes.typeid=invMetaTypes.typeid and attributeID=633) 
        WHERE metaGroupID=1 
        AND (parenttypeid=:typeid 
        OR parenttypeid in (select parenttypeid from $this->schemaName.invMetaTypes where typeid=:typeid)
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $versions=array();
        while ($row = $stmt->fetchObject()) {
            $versions[$row->level]=array("name"=>$row->typename,"typeid"=>$row->typeid);
        }
        return $versions;
    }

    public function inventionChance($typeid)
    {
        $sql=<<<EOS
        SELECT max(CASE
        WHEN t.groupID IN (419,27) OR t.typeID = 17476
        THEN 0.20
        WHEN t.groupID IN (26,28) OR t.typeID = 17478
        THEN 0.25
        WHEN t.groupID IN (25,420,513) OR t.typeID = 17480
        THEN 0.30
        WHEN EXISTS (SELECT * FROM $this->schemaName.invMetaTypes WHERE parentTypeID = t.typeID AND metaGroupID = 2)
        THEN 0.40
        ELSE 0.00
        END) chance
        FROM $this->schemaName.invTypes t 
        WHERE typeid in (select parenttypeid from $this->schemaName.invMetaTypes where typeid=:typeid) or typeid=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $chance=0;
        while ($row = $stmt->fetchObject()) {
            $chance=(float)$row->chance;
        }
        return $chance;
    }
    
    public function decryptors($typeid)
    {
        $sql=<<<EOS
        SELECT it2.typeid,it2.typename,coalesce(dta2.valueint,dta2.valueFloat) modifier
        FROM $this->schemaName.invBlueprintTypes ibt 
        JOIN $this->schemaName.ramTypeRequirements rtr on (ibt.blueprinttypeid=rtr.typeid)
        JOIN $this->schemaName.invTypes it1 on (rtr.requiredTypeID=it1.typeid and it1.groupid=716  and activityid=8)
        JOIN $this->schemaName.dgmTypeAttributes dta on ( it1.typeid=dta.typeid and dta.attributeid=1115)
        JOIN $this->schemaName.invTypes it2 on (it2.groupid=coalesce(dta.valueint,dta.valueFloat))
        JOIN $this->schemaName.dgmTypeAttributes dta2 on (dta2.typeid=it2.typeid and dta2.attributeid=1112)
        WHERE ibt.producttypeid=:typeid
        OR ibt.producttypeid in (select parenttypeid from $this->schemaName.invMetaTypes where typeid=:typeid)
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $decryptors=array();
        while ($row = $stmt->fetchObject()) {
            $decryptors[$row->modifier]=array("name"=>$row->typename,"typeid"=>$row->typeid);
        }
        return $decryptors;
    }
}
