<?php

use App\Libraries\MedicineType;
use CodeIgniter\Test\CIUnitTestCase;

final class MedicineTypeTest extends CIUnitTestCase
{
    public function testUnspecifiedFilterGroupsNullAndEmptyWithoutLeakingOtherBranches(): void
    {
        $db = new \CodeIgniter\Database\SQLite3\Connection(['database'=>':memory:','DBDriver'=>'SQLite3','DBDebug'=>true]);
        $db->initialize();
        try {
            $db->query('CREATE TABLE products (id INTEGER PRIMARY KEY, branch_id INTEGER, medicine_type TEXT, generic_name TEXT)');
            foreach ([1=>'branded',2=>'generic',3=>null,4=>'',5=>null,6=>'non_medicine'] as $id=>$type) {
                $db->table('products')->insert(['id'=>$id,'branch_id'=>$id===5?2:1,'medicine_type'=>$type,'generic_name'=>'Paracetamol']);
            }
            foreach (['branded'=>[1],'generic'=>[2],'non_medicine'=>[6],'unspecified'=>[3,4],''=>[1,2,3,4,6]] as $filter=>$ids) {
                $query = MedicineType::applyFilter($db->table('products')->where('branch_id',1),$filter);
                $actual = array_map('intval', array_column($query->orderBy('id')->get()->getResultArray(),'id'));
                $this->assertSame($ids,$actual);
            }
            $this->assertSame('', MedicineType::filter(['branded']));
            $this->assertSame('', MedicineType::filter('invalid'));
            $this->assertSame('Non-medicine', MedicineType::label('non_medicine'));
            $this->assertSame('non_medicine', MedicineType::filter('non_medicine'));
        } finally { $db->close(); }
    }

    public function testMigrationPreservesUnknownClassificationsAndExistingDataOnRepeat(): void
    {
        $db = new \CodeIgniter\Database\SQLite3\Connection(['database'=>':memory:','DBDriver'=>'SQLite3','DBDebug'=>true]);
        $db->initialize();
        try {
            $db->query('CREATE TABLE products (id INTEGER PRIMARY KEY, product_name TEXT, generic_name TEXT, price NUMERIC)');
            $db->table('products')->insert(['id'=>1,'product_name'=>'Named medicine','generic_name'=>'Paracetamol','price'=>50]);
            require_once APPPATH . 'Database/Migrations/2026_10_14_000012_add_product_medicine_type.php';
            $migration = new \App\Database\Migrations\AddProductMedicineType(new \CodeIgniter\Database\SQLite3\Forge($db));
            $migration->up();
            $row = $db->table('products')->get()->getRowArray();
            $this->assertNull($row['medicine_type']);
            $this->assertSame('Not specified', MedicineType::label($row['medicine_type']));
            $db->table('products')->where('id',1)->update(['medicine_type'=>'branded']);
            $migration->up();
            $row = $db->table('products')->get()->getRowArray();
            $this->assertSame('branded',$row['medicine_type']);
            $this->assertSame('Paracetamol',$row['generic_name']);
            $this->assertEquals(50,$row['price']);
        } finally { $db->close(); }
    }
}
