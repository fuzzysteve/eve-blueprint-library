<?php
namespace EveBlueprint\DatabaseVendor;

class Mysql
{

    private $schemaName;
    private $dbh;

    public function __construct(\PDO $dbh, $schemaName = 'eve')
    {
        $query=$dbh->query("select count(*) from $schemaName.industryActivity");
        
        if (!($query)) {
            throw new \Exception("$schemaName does not contain industryActivity");
        }
        $query->closeCursor();
        $this->schemaName=$schemaName;
        $this->dbh=$dbh;

    }
    
    public function checkTypeID($typeid)
    {
        $sql=<<<EOS
        select typeid from  $this->schemaName.industryBlueprints where typeid=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $checkedid=0;
        while ($row = $stmt->fetchObject()) {
            $checkedid=$row->typeid;
        }
        if (!$checkedid) {
            $sql=<<<EOS
            select typeid from $this->schemaName.industryActivityProducts
            where productTypeID=:typeid
            and activityID=1;
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            while ($row = $stmt->fetchObject()) {
                $checkedid=$row->typeid;
            }
        }
        return $checkedid;

    }


    public function blueprintSkills($typeid)
    {
        $sql=<<<EOS
        select activityID,skillID,typeName,level 
        from $this->schemaName.industryActivitySkills
        join $this->schemaName.invTypes on industryActivitySkills.skillID=invTypes.typeID
        where 
        industryActivitySkills.typeID=:typeid
        order by activityID
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $skills=array();
        while ($row = $stmt->fetchObject()) {
            $skills[(int)$row->activityID][]=array(
                "typeid"=>(int)$row->skillID,
                "name"=>$row->typeName,
                "level"=>(int)$row->level
            );
        }

        if (!isset($skills[8])) {
            $sql=<<<EOS
            select industryActivitySkills.activityID,skillID,typeName,level
            from $this->schemaName.industryActivitySkills
            join $this->schemaName.invTypes on industryActivitySkills.skillID=invTypes.typeID
            join $this->schemaName.industryActivityProducts on 
                (industryActivityProducts.typeID=industryActivitySkills.typeID 
                and industryActivityProducts.productTypeID=:typeid)
            where
            industryActivitySkills.activityID=8
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            while ($row = $stmt->fetchObject()) {
                $skills[(int)$row->activityID][]=array(
                    "typeid"=>(int)$row->skillID,
                    "name"=>$row->typeName,
                    "level"=>(int)$row->level
                );
            }
        }
        return $skills;
    }
    
    public function activityMaterials($typeid)
    {
        $sql=<<<EOS
        SELECT it.typeName name, it.typeid,iam.quantity quantity,consume,iam.activityID,coalesce(iap.typeid,-1) maketype
        FROM $this->schemaName.industryActivityMaterials iam
        join $this->schemaName.invTypes it on (iam.materialTypeID = it.typeID)
        left join $this->schemaName.industryActivityProducts iap on (iam.materialTypeID=iap.productTypeID)
        where
        iam.typeID=:typeid 
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $materials=array();
        while ($row = $stmt->fetchObject()) {
            $materials[$row->activityID][]=array(
                "typeid"=>(int)$row->typeid,
                "name"=>$row->name,
                "quantity"=>(int)$row->quantity,
                "consume"=>(int)$row->consume,
                "maketype"=>(int)$row->maketype
            );
        }
        if (!isset($materials[8])) {
            $sql=<<<EOS
            SELECT it.typeName name, it.typeid,iam.quantity quantity,consume,iam.activityID
            FROM $this->schemaName.industryActivityMaterials iam
            join $this->schemaName.invTypes it on (iam.materialTypeID = it.typeID)
            join $this->schemaName.industryActivityProducts on
              (industryActivityProducts.typeID=iam.typeID
               and industryActivityProducts.productTypeID=:typeid)
            where
            iam.activityID=8
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            while ($row = $stmt->fetchObject()) {
                $materials[$row->activityID][]=array(
                    "typeid"=>(int)$row->typeid,
                    "name"=>$row->name,
                    "quantity"=>(int)$row->quantity,
                    "consume"=>(int)$row->consume,
                );
            }
        }
        return $materials;
    }

    public function blueprintDetails($typeid)
    {
        $sql=<<<EOS
        select industryActivity.activityID,time from $this->schemaName.industryActivity where typeID=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $times=array();
        while ($row = $stmt->fetchObject()) {
            $times[$row->activityID]=$row->time;
        }
        if (!isset($times[8])) {
            $sql=<<<EOS
            select activityID,time
            from $this->schemaName.industryActivity
            join $this->schemaName.industryActivityProducts on
                (industryActivityProducts.typeID=industryActivity.typeID
                 and industryActivityProducts.productTypeID=:typeid)
            where
            industryActivity.activityID=8
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            while ($row = $stmt->fetchObject()) {
                $times[$row->activityID]=$row->time;
            }
        }
        $sql=<<<EOS
         select maxProductionLimit,iap.producttypeid,typename,iap.quantity,coalesce(metaGroupID,1) techLevel
         from $this->schemaName.industryBlueprints ib
         join $this->schemaName.industryActivityProducts iap on (ib.typeID=iap.typeID and activityID=1)
         join $this->schemaName.invTypes on (iap.productTypeID=invTypes.typeid)
         left join $this->schemaName.invMetaTypes on (iap.productTypeID=invMetaTypes.typeid)
         where ib.typeID=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $row = $stmt->fetchObject();
        $details=array();
        $details['maxProductionLimit']=$row->maxProductionLimit;
        $details['productTypeID']=$row->producttypeid;
        $details['productTypeName']=$row->typename;
        $details['productQuantity']=$row->quantity;
        $details['times']=$times;
        $details['techLevel']=$row->techLevel;
        $sql=<<<EOS
        SELECT sum(quantity*adjustedprice) price 
        FROM $this->schemaName.industryActivityMaterials iam 
        JOIN evesupport.priceData ON (materialtypeid=priceData.typeid) 
        WHERE activityID=1 AND iam.typeid=:typeid
EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $row = $stmt->fetchObject();
        $details['adjustedPrice']=$row->price;
        if ($details['techLevel']==2) {
            $sql=<<<EOS
            SELECT sum(iam.quantity*adjustedprice) price,iam.typeid
            FROM $this->schemaName.industryActivityMaterials iam
            JOIN evesupport.priceData ON (materialtypeid=priceData.typeid)
            JOIN $this->schemaName.industryActivityProducts iap ON (iap.typeid=iam.typeid)
            WHERE iam.activityID=1 AND iap.producttypeid=:typeid
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            $row = $stmt->fetchObject();
            if (!is_null($row->price)) {
                $details['precursorAdjustedPrice']=$row->price;
                $details['precursorTypeId']=$row->typeid;
            }
            $sql=<<<EOS
            SELECT probability
            FROM industryActivityProbabilities
            WHERE producttypeid=:typeid
            AND activityID=8
EOS;
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(":typeid"=>$typeid));
            $row = $stmt->fetchObject();
            if (!is_null($row->probability)) {
                $details['probability']=$row->probability;
            }
        }
        return $details;
    }

    public function decryptors()
    {
        $sql=<<<EOS
        SELECT distinct
        it2.typename,
        it2.typeid,
        COALESCE(dta2.valueInt,dta2.valueFloat) multiplier,
        COALESCE(dta3.valueInt,dta3.valueFloat) me,
        COALESCE(dta4.valueInt,dta4.valueFloat) te,
        COALESCE(dta5.valueInt,dta5.valueFloat) runs
        FROM $this->schemaName.invTypes it2
        JOIN $this->schemaName.dgmTypeAttributes dta2 on (dta2.typeid=it2.typeid and dta2.attributeID=1112)
        JOIN $this->schemaName.dgmTypeAttributes dta3 on (dta3.typeid=it2.typeid and dta3.attributeID=1113)
        JOIN $this->schemaName.dgmTypeAttributes dta4 on (dta4.typeid=it2.typeid and dta4.attributeID=1114)
        JOIN $this->schemaName.dgmTypeAttributes dta5 on (dta5.typeid=it2.typeid and dta5.attributeID=1124)
        WHERE it2.groupid=1304;

EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();
        $decryptors=array();
        while ($row = $stmt->fetchObject()) {
            $decryptors[]=array(
                "name"=>$row->typename,
                "typeid"=>$row->typeid,
                "multiplier"=>$row->multiplier,
                "me"=>$row->me,
                "te"=>$row->te,
                "runs"=>$row->runs);
        }
        return $decryptors;
    }
}
