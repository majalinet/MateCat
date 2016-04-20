<?php

/**
 * @group regression
 * @covers Engine::getInstance
 * User: dinies
 * Date: 14/04/16
 * Time: 17.45
 */
class GetInstanceTest extends AbstractTest
{
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_insert_user;
    protected $sql_insert_engine;
    protected $sql_delete_user;
    protected $sql_delete_engine;

    public function setUp()
    {

        parent::setUp();
        $this->database_instance=Database::obtain();
        $this->sql_insert_user = "INSERT INTO ".INIT::$DB_DATABASE.".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES ('44', 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo', '');";
        $this->sql_insert_engine = "INSERT INTO ".INIT::$DB_DATABASE.".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', '44');";
        $this->database_instance->query($this->sql_insert_user);
        $this->database_instance->query($this->sql_insert_engine);
        $this->sql_delete_user ="DELETE FROM users WHERE uid='44';";
        $this->sql_delete_engine ="DELETE FROM engines WHERE id='10';";
    }

    public function tearDown()
    {
        
        $this->database_instance->query($this->sql_delete_user);
        $this->database_instance->query($this->sql_delete_engine);
        $flusher= new Predis\Client(INIT::$REDIS_SERVERS);
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * @param id of the engine previously constructed
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_constructed_engine(){

        $engine = Engine::getInstance(10);
        $this->assertTrue($engine instanceof Engines_DeepLingo);
    }


    /**
     * @param id of the engine previously constructed
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_constructed_engine_my_memory(){

        $engine = Engine::getInstance(1);
        $this->assertTrue($engine instanceof Engines_MyMemory);
    }

    /**
     * @param id of the engine previously constructed
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_default_engine(){
        $this->setExpectedException("Exception");
        Engine::getInstance(0);

    }

    /**
     * @param ''
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_whit_out_id(){

        $this->setExpectedException('Exception');
        Engine::getInstance('');
    }
    /**
     * @param null
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_whit_null_id(){

        $this->setExpectedException('Exception');
        Engine::getInstance(null);
    }
    /**
     * @param 99
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_with_no_mach_for_engine_id(){

        $this->setExpectedException('Exception');
        Engine::getInstance(99);
    }

    /**
     * verify that the method  name of engine not match the classes of known engines
     * @group regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_with_no_mach_for_engine_class_name(){
       
        $sql_update_engine_class_name="UPDATE `unittest_matecat_local`.`engines` SET class_load='YourMemory' WHERE id='10';";

        require_once 'Predis/autoload.php';
        $obliterator= new Predis\Client(INIT::$REDIS_SERVERS);
        $obliterator->del($sql_update_engine_class_name);
        $this->database_instance->query($sql_update_engine_class_name);
        $this->setExpectedException('\Exception');
        Engine::getInstance(10);
    }

    
    
}