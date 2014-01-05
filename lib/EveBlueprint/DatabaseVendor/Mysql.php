<?php
namespace EveBlueprint\DatabaseVendor;

class Mysql
{

    private $schemaName;
    private $supportSchema;
    private $dbh;

    public function __construct(\PDO $dbh, $schemaName = 'eve', $supportSchema = 'evesupport')
    {
        $query=$dbh->query("select count(*) from $schemaName.invBlueprintTypes");
        
        if (!($query)) {
            throw new Exception("$schemaName does not contain invBlueprintTypes");
        }
        $query->closeCursor();
        $query=$dbh->query("select count(*) from $supportSchema.inventionChance");
        if (!($query)) {
            throw new Exception("$supportSchema does not contain inventionChance");
        }
        $query->closeCursor();
        $this->schemaName=$schemaName;
        $this->supportSchema=$supportSchema;
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
                $basematerials[]=array("typeid"=>$row->typeid,"name"=>$row->name,"quantity"=>$row->quantity);
            }
        }
        return $basematerials;
    }

    public function extraMaterials($typeid)
    {
        $sql=<<<EOS
        SELECT t.typeName name, r.quantity quantity, r.damagePerJob damage,t.typeID typeid 
        FROM $this->schemaName.ramTypeRequirements r
        join $this->schemaName.invTypes t on (r.requiredTypeID = t.typeID)
        join $this->schemaName.invBlueprintTypes bt on ( r.typeID = bt.blueprintTypeID)
        join $this->schemaName.invGroups g on (t.groupID = g.groupID) 
        where 
        r.activityID = 1 
        and bt.productTypeID=:typeid 
        and g.categoryID != 16
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $extramaterials=array();
        while ($row = $stmt->fetchObject()) {
            if ($row->quantity>0) {
                $extramaterials[]=array(
                    "typeid"=>$row->typeid,
                    "name"=>$row->name,
                    "quantity"=>$row->quantity,
                    "damage"=>$row->damage
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
            $skills[$row->activityid][]=array(
                "typeid"=>$row->typeid,
                "name"=>$row->name,
                "level"=>$row->level
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
                            "typeid"=>$row->value,
                            "name"=>$row->typename
                        );
                        break;
                    case 277:
                        $holder[$row->typeid][182]["level"]=$row->value;
                        break;
                    case 278:
                        $holder[$row->typeid][183]["level"]=$row->value;
                        break;
                    case 279:
                        $holder[$row->typeid][184]["level"]=$row->value;
                        break;
                    case 1286:
                        $holder[$row->typeid][1285]["level"]=$row->value;
                        break;
                    case 1287:
                        $holder[$row->typeid][1289]["level"]=$row->value;
                        break;
                    case 1288:
                        $holder[$row->typeid][1290]["level"]=$row->value;
                        break;
                }
            }
            foreach ($holder as $key => $value) {
                foreach ($value as $attributeid => $details) {
                    $skills[8][]=
                    array(
                        "typeid"=>$details["skill"]["typeid"],
                        "name"=>$details["skill"]["name"],
                        "level"=>$details["level"]
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
                "typeid"=>$row->typeid,
                "name"=>$row->name,
                "quantity"=>$row->quantity,
                "damage"=>$damage,
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
}
