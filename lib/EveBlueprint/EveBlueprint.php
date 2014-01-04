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
        if ((!isset($typeid) && is_numeric($typeid))) {
            $typeid=$this->typeid;
        }
        $basematerials=array();
        $basematerials=$this->sql->baseMaterials($typeid);
        return $basematerials;
    }
}
