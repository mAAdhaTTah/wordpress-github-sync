<?php
/**
 * Locks and unlock the import/export process.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Semaphore
 */
class WordPress_GitHub_Sync_Semaphore {

	/**
	 * Sempahore's option key.
	 */
	const OPTION = 'wpghs_semaphore_lock';

	/**
	 * Option key when semaphore is locked.
	 */
	const LOCKED = 'yes';

	/**
	 * Option key when semaphore is unlocked.
	 */
	const UNLOCKED = 'no';

	/**
	 * Checks if the Semaphore is open.
	 *
	 * Fails to report it's open if the the Api class can't make a call
	 * or the push lock has been enabled.
	 *
	 * @return bool
	 */
	public function is_open() {
		if ( self::LOCKED === get_option( self::OPTION ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Enables the push lock.
	 */
	public function lock() {
		update_option( self::OPTION, self::LOCKED );
	}

	/**
	 * Disables the push lock.
	 */
	public function unlock() {
		update_option( self::OPTION, self::UNLOCKED );
	}
}
