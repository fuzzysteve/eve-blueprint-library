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
                throw new Exception('Database type not handled. please write a new DatabaseVendor class for it');
        }
                



        if (is_numeric($typeid)) {
            $this->typeid = $typeid;
        } else {
            throw new Exception('Typeid must be a number');
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
    
    public function skills($typeid = null)
    {
        if (is_null($typeid) && !is_numeric($typeid)) {
            $typeid=$this->typeid;
        }
        if ($typeid==$this->typeid & isset($this->cachedSkills)) {
            return $this->cachedSkills;
        }

        $skills=array();
        $skills=$this->sql->skills($typeid);
        if ($typeid==$this->typeid) {
            $this->cachedSkills=$skills;
        }
        return $skills;
    }
}
