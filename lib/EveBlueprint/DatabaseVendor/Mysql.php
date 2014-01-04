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
}
