<?php
namespace EveBlueprint\DatabaseVendor;

class Mysql
{

    private $schemaName;
    private $dbh;

    public function __construct(\PDO $dbh, $schemaName = 'sdecrius1')
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
        error_log($typeid."skills");
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
        SELECT it.typeName name, it.typeid,quantity quantity,consume,activityID
        FROM $this->schemaName.industryActivityMaterials iam
        join $this->schemaName.invTypes it on (iam.materialTypeID = it.typeID)
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
            SELECT sum(iam.quantity*adjustedprice) price
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
        AND parenttypeid in 
            (SELECT parenttypeid FROM  $this->schemaName.invMetaTypes 
             WHERE parentTypeID = 
                (SELECT productTypeID from  $this->schemaName.industryActivityProducts where typeID=:typeid 
                    and activityID=1 limit 1)
            OR typeID = 
                (SELECT productTypeID from  $this->schemaName.industryActivityProducts where typeID=:typeid 
                    and activityID=1 limit 1)
            )

EOS;
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute(array(":typeid"=>$typeid));
        $versions=array();
        while ($row = $stmt->fetchObject()) {
            $versions[$row->level]=array("name"=>$row->typename,"typeid"=>$row->typeid);
        }
        return $versions;
    }
}
