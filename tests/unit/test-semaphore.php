<?php

/**
 * @group semaphore
 */
class WordPress_GitHub_Sync_Semaphore_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();

		$this->semaphore = new WordPress_GitHub_Sync_Semaphore( $this->app );
	}

	public function test_should_default_to_open() {
		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_unlock() {
		set_transient( WordPress_GitHub_Sync_Semaphore::KEY, WordPress_GitHub_Sync_Semaphore::VALUE_LOCKED );

		$this->semaphore->unlock();

		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_lock() {
		set_transient( WordPress_GitHub_Sync_Semaphore::KEY, WordPress_GitHub_Sync_Semaphore::VALUE_UNLOCKED );

		$this->semaphore->lock();

		$this->assertFalse( $this->semaphore->is_open() );
	}

	public function test_should_expire() {
		$this->semaphore->lock();

		sleep( MINUTE_IN_SECONDS );
		// A little extra to make sure it's expired.
		sleep( 5 );

		$this->assertTrue( $this->semaphore->is_open() );
	}
}
