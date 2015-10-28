<?php

class WordPress_GitHub_Sync_Semaphore_Test extends WordPress_GitHub_Sync_TestCase {
	public function setUp() {
		parent::setUp();

		$this->semaphore = new WordPress_GitHub_Sync_Semaphore( $this->app );
	}

	public function test_should_default_to_open() {
		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_unlock() {
		update_option( WordPress_GitHub_Sync_Semaphore::OPTION, WordPress_GitHub_Sync_Semaphore::LOCKED );

		$this->semaphore->unlock();

		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_lock() {
		update_option( WordPress_GitHub_Sync_Semaphore::OPTION, WordPress_GitHub_Sync_Semaphore::UNLOCKED );

		$this->semaphore->lock();

		$this->assertFalse( $this->semaphore->is_open() );
	}
}
