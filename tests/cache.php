<?php

/**
 * tests for Hm_Page_Cache
 */
class Hm_Test_Page_Cache extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        $this->page_cache = new Hm_Page_Cache();
        $this->page_cache->add('foo', 'bar');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $this->assertEquals('bar', $this->page_cache->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->assertEquals(array('foo' => array('bar', false)), $this->page_cache->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        $this->assertEquals(1, count($this->page_cache->dump()));
        $this->page_cache->add('bar', 'foo');
        $this->assertEquals(2, count($this->page_cache->dump()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_concat() {
        $this->assertEquals('bar', $this->page_cache->get('foo'));
        $this->page_cache->concat('foo', 'foo');
        $this->assertEquals('barfoo', $this->page_cache->get('foo'));
        $this->page_cache->concat('foo', 'bar', false, ':');
        $this->assertEquals('barfoo:bar', $this->page_cache->get('foo'));
        $this->page_cache->concat('baz', 'baz');
        $this->assertEquals('baz', $this->page_cache->get('baz'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $this->assertEquals('bar', $this->page_cache->get('foo'));
        $this->assertTrue($this->page_cache->del('foo'));
        $this->assertFalse($this->page_cache->get('foo'));
        $this->assertFalse($this->page_cache->del('blah'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flush() {
        $session = new Hm_Mock_Session();
        $this->assertEquals(array('foo' => array('bar', false)), $this->page_cache->dump());
        $this->page_cache->flush($session);
        $this->assertEquals(array(), $this->page_cache->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load() {
        $session = new Hm_Mock_Session();
        $this->page_cache->flush($session);
        $this->page_cache->load($session);
        $this->assertEquals(array('foo' => array('bar', false)), $this->page_cache->dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save() {
        $session = new Hm_Mock_Session();
        $this->page_cache->add('bar', 'foo', true);
        $this->page_cache->save($session);
        $this->assertEquals(array('foo' => array('bar', false)), $session->data['page_cache']);
        $this->assertEquals(array('bar' => array('foo', true)), $session->data['saved_pages']);
    }
    public function tearDown() {
        unset($this->page_cache);
    }
}

/**
 * tests for Hm_Uid_Cache
 */
class Hm_Test_Uid_Cache extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        Test_Uid_Cache::load(array(array('foo', 'bar'),array()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_is_read() {
        $this->assertTrue(Test_Uid_Cache::is_read('foo'));
        $this->assertTrue(Test_Uid_Cache::is_read('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_is_unread() {
        $this->assertFalse(Test_Uid_Cache::is_unread('foo'));
        Test_Uid_Cache::unread('bar');
        $this->assertTrue(Test_Uid_Cache::is_unread('bar'));
        $this->assertFalse(Test_Uid_Cache::is_read('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_load() {
        Test_Uid_Cache::load(array(array(),array()));
        $this->assertFalse(Test_Uid_Cache::is_read('foobar'));
        Test_Uid_Cache::load(array(array('foobar'), array('foobar')));
        $this->assertTrue(Test_Uid_Cache::is_read('foobar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_dump() {
        $this->assertEquals(array(array('foo', 'bar'),array()), Test_Uid_Cache::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_read() {
        $this->assertEquals(false, Test_Uid_Cache::is_read('baz'));
        Test_Uid_Cache::unread('baz');
        Test_Uid_Cache::read('baz');
        $this->assertTrue(Test_Uid_Cache::is_read('baz'));
    }
    public function tearDown() {
        Test_Uid_Cache::load(array(),array());
    }
}
