<?php
namespace EveBlueprint;

use EveBlueprint\DatabaseVendor\Mysql;

class EveBlueprint
{

    public static $version = "1.0.0";

    /*
     *  PDO database handle.
     */

    private $dbh;

    private $typeid;

    private $sql;

    private $cachedSkills;
    private $cachedActivityMaterials;
    private $cachedDetails;
    private $cachedDecryptors;
    private $cachedMetaVersions;
    private $checkedIDs;

    public function __construct(\PDO $dbh, $typeid = 0)
    {
        
        $this->dbh = $dbh;

        switch ($this->dbh->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $this->sql=new \EveBlueprint\DatabaseVendor\Mysql($dbh);
                break;
            case 'postgres':
                $this->sql=new \EveBlueprint\DatabaseVendor\Postgres();
                break;
            case 'postgres':
                $this->sql=new \EveBlueprint\DatabaseVendor\Sqlite();
                break;
            default:
                throw new \Exception('Database type not handled. please write a new DatabaseVendor class for it');
        }
                



        if (is_numeric($typeid) and $typeid>0) {
            $this->typeid = $this->checkTypeID($typeid);
        } else {
            throw new \Exception('Typeid must be a number');
        }
        error_log($this->typeid);
    }

    public function checkTypeID($typeid)
    {
        if (isset($this->checkedIDs[$typeid])) {
            return $this->checkedIDs[$typeid];
        }
        $confirmedID=$this->sql->checkTypeID($typeid);
        if (!$confirmedID) {
            throw new \Exception("No such blueprint or produce exists");
        }
        $this->checkedIDs[$typeid]=$confirmedID;
        return $this->checkedIDs[$typeid];
    }

    
    public function blueprintSkills($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (($typeid==$this->typeid) and isset($this->cachedSkills)) {
            return $this->cachedSkills;
        }

        $skills=$this->sql->blueprintSkills($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedSkills=$skills;
        }
        return $skills;
    }
    
    
    public function activityMaterials($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (($typeid==$this->typeid) and isset($this->cachedActivityMaterials)) {
            return $this->cachedActivityMaterials;
        }

        $activitymaterials=$this->sql->activityMaterials($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedActivityMaterials=$activitymaterials;
        }
        return $activitymaterials;
    }

    public function metaVersions($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (($typeid==$this->typeid) and isset($this->cachedMetaVersions)) {
            return $this->cachedMetaVersions;
        }

        $metaversions=$this->sql->metaVersions($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedMetaVersions=$metaversions;
        }
        return $metaversions;
    }

    public function decryptors($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (($typeid==$this->typeid) and isset($this->cachedDecryptors)) {
            return $this->cachedDecryptors;
        }

        $decryptors=$this->sql->decryptors($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedDecryptors=$decryptors;
        }
        return $decryptors;
    }


    public function blueprintDetails($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (($typeid==$this->typeid) & isset($this->cachedDetails)) {
            return $this->cachedDetails;
        }

        $details=$this->sql->blueprintDetails($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedDetails=$details;
        }
        return $details;
    }

    public function changeTypeID($typeid)
    {
        if (!is_numeric($typeid)) {
            throw new \Exception("TypeID must be numeric");
        }
        $this->typeid=$this->sql->checkTypeID($typeid);
        unset($this->cachedSkills);
        unset($this->cachedActivityMaterials);
        unset($this->cachedDetails);
    }
}
