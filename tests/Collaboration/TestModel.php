<?php

namespace PhpPlatform\Tests\Collaboration;


use PhpPlatform\Tests\Collaboration\SampleModels\SampleModel;

class TestModel extends TestBase{
    

    public function testConstruct(){

        // test for no exceptions while constructing
        $isException = false;
        try{
            $tSample = new SampleModel();
        }catch (\Exception $e){
            echo $e;
            $isException = true;
        }
        $this->assertTrue(!$isException);

        $TSampleReflection = new \ReflectionClass(get_class($tSample));

        // test for constructor with argument
        $tSample = new SampleModel(1);

        $fProperty1Reflection = $TSampleReflection->getProperty("property1");
        $fProperty1Reflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("sample",0,'property1'),$fProperty1Reflection->getValue($tSample));

        $fProperty2Reflection = $TSampleReflection->getProperty("property2");
        $fProperty2Reflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("sample",0,'property2'),$fProperty2Reflection->getValue($tSample));

    }

}