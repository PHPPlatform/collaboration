<?php

namespace PhpPlatform\Tests\Collaboration;


use PhpPlatform\Tests\Collaboration\SampleModels\SampleModel;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;

class TestModel extends TestBase{
    

    public function testConstruct(){

        // test for with empty arguments
        $isException = false;
        try{
            $tSample = new SampleModel();
        }catch (BadQueryException $e){
            $isException = true;
        }
        $this->assertTrue($isException);

        $TSampleReflection = new \ReflectionClass('PhpPlatform\Tests\Collaboration\SampleModels\SampleModel');

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