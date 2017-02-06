<?php
/**
 * User: Raaghu
 * Date: 27-09-2015
 * Time: PM 04:36
 */

namespace PhpPlatform\Tests\Collaboration\SampleModels;


use PhpPlatform\Collaboration\Model;

/**
 * Class SampleClass
 * @tableName sample
 * @prefix sample
 */
class SampleModel extends Model{

    /**
     * @columnName property1
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $property1 = null;

    /**
     * @columnName property2
     * @type varchar
     * @get
     */
    private $property2 = null;


    public function __construct($property1 = null){
        $this->property1 = $property1;
        parent::__construct();
    }
    
    

}