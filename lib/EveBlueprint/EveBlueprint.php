<?php
namespace EveBlueprint;

use EveBlueprint\DatabaseVendor\Mysql;
use EveBlueprint\DatabaseVendor\Postgres;
use EveBlueprint\DatabaseVendor\Sqlite;

class EveBlueprint
{

    public static $version = "1.0.0";

    /*
     *  PDO database handle.
     */

    private $dbh;

    private $typeid;

    private $sql;

    private $cachedBase;
    private $cachedExtra;
    private $cachedSkills;
    private $cachedActivityMaterials;
    private $cachedDetails;

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
                



        if (is_numeric($typeid)) {
            $this->typeid = $typeid;
        } else {
            throw new \Exception('Typeid must be a number');
        }
    }

    public function baseMaterials($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if ($typeid==$this->typeid & isset($this->cachedBase)) {
            return $this->cachedBase;
        }

        $basematerials=array();
        $basematerials=$this->sql->baseMaterials($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedBase=$basematerials;
        }
        return $basematerials;
    }

    public function extraMaterials($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if ($typeid==$this->typeid & isset($this->cachedExtra)) {
            return $this->cachedExtra;
        }

        $extramaterials=array();
        $extramaterials=$this->sql->extraMaterials($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedExtra=$extramaterials;
        }
        return $extramaterials;
    }
    
    public function blueprintSkills($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if ($typeid==$this->typeid & isset($this->cachedSkills)) {
            return $this->cachedSkills;
        }

        $skills=array();
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
        if ($typeid==$this->typeid & isset($this->cachedActivityMaterials)) {
            return $this->cachedActivityMaterials;
        }

        $activitymaterials=array();
        $activitymaterials=$this->sql->activityMaterials($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedActivityMaterials=$activitymaterials;
        }
        return $activitymaterials;
    }

    public function blueprintDetails($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if ($typeid==$this->typeid & isset($this->cachedDetails)) {
            return $this->cachedDetails;
        }

        $details=array();
        $details=$this->sql->blueprintDetails($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedDetails=$details;
        }
        return $details;
    }

    public function baseWithMePe(\EveCharacter\EveCharacter $character, $me, $typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if (!is_numeric($me)) {
            throw new \Exception("ME must be numeric");
        }
        $details=$this->blueprintDetails($typeid);
        $pe=$character->getSkill(3388);
        $basematerials=$this->baseMaterials($typeid);
        if ($me<0) {
            $wastage=(($details['wasteFactor']/100)*(1-$me));
        } else {
            $wastage=(($details['wasteFactor']/(1+$me))/100);
        }

        $withme=array();
        foreach ($basematerials as $material) {
            $withme[]=array(
                "typeid"=>(int)$material["typeid"],
                "name"=>$material["name"],
                "perfect"=>(int)$material["quantity"],
                "withme"=>(int)($material["quantity"]+round($material["quantity"]*$wastage)),
                "you"=>(int)($material["quantity"]+round($material["quantity"]*$wastage)
                +round($material["quantity"]*(0.25-(0.05*$pe))))
                );
        }
        return $withme;
    }


    public function extraWithPe(\EveCharacter\EveCharacter $character, $typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        $pe=$character->getSkill(3388);
        $extramaterials=$this->extraMaterials($typeid);
        $withpe=array();
        foreach ($extramaterials as $material) {
            $pewaste=0;
            if (1==$material["baseMaterial"]) {
                $pewaste=round($material["quantity"]*(0.25-(0.05*$pe)));
            }
            $withpe[]=array(
                "typeid"=>(int)$material["typeid"],
                "name"=>$material["name"],
                "perfect"=>(int)$material["quantity"],
                "withpe"=>(int)($material["quantity"]+$pewaste),
                "damage"=>(float)$material["damage"],
                "baseMaterial"=>(int)$material["baseMaterial"]
            );
        }
        return $withpe;
    }

    public function changeTypeID($typeid)
    {
        if (!is_numeric($typeid)) {
            throw new \Exception("TypeID must be numeric");
        }
        $this->typeid=$typeid;
        unset($this->cachedBase);
        unset($this->cachedExtra);
        unset($this->cachedSkills);
        unset($this->cachedActivityMaterials);
        unset($this->cachedDetails);
    }
}
