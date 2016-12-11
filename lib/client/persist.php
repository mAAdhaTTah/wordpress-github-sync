<?php
/**
 * API Persist client.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Persist_Client
 */
class WordPress_GitHub_Sync_Persist_Client extends WordPress_GitHub_Sync_Base_Client {

	/**
	 * Add a new commit to the master branch.
	 *
	 * @param WordPress_GitHub_Sync_Commit $commit Commit to create.
	 *
	 * @return bool|mixed|WordPress_GitHub_Sync_Commit|WP_Error
	 */
	public function commit( WordPress_GitHub_Sync_Commit $commit ) {
		if ( ! $commit->tree()->is_changed() ) {
			return new WP_Error(
				'no_commit',
				__(
					'There were no changes, so no additional commit was added.',
					'wp-github-sync'
				)
			);
		}

		$tree = $this->create_tree( $commit->tree() );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$commit->tree()->set_sha( $tree->sha );
		$commit = $this->create_commit( $commit );

		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		$ref = $this->set_ref( $commit->sha );

		if ( is_wp_error( $ref ) ) {
			return $ref;
		}

		return true;
	}

	/**
	 * Create the tree by a set of blob ids.
	 *
	 * @param WordPress_GitHub_Sync_Tree $tree Tree to create.
	 *
	 * @return stdClass|WP_Error
	 */
	protected function create_tree( WordPress_GitHub_Sync_Tree $tree ) {
		return $this->call( 'POST', $this->tree_endpoint(), $tree->to_body() );
	}

	/**
	 * Create the commit from tree sha.
	 *
	 * @param WordPress_GitHub_Sync_Commit $commit Commit to create.
	 *
	 * @return mixed
	 */
	protected function create_commit( WordPress_GitHub_Sync_Commit $commit ) {
		$body = $commit->to_body();

		if ( $author = $this->export_user() ) {
			$body['author'] = $author;
		}

		return $this->call( 'POST', $this->commit_endpoint(), $body );
	}

	/**
	 * Updates the master branch to point to the new commit
	 *
	 * @param string $sha Sha for the commit for the master branch.
	 *
	 * @return mixed
	 */
	protected function set_ref( $sha ) {
		return $this->call( 'PATCH', $this->reference_endpoint(), array( 'sha' => $sha ) );
	}

	/**
	 * Get the data for the current user.
	 *
	 * @return array
	 */
	protected function export_user() {
		// @todo constant/abstract out?
		if ( $user_id = (int) get_option( '_wpghs_export_user_id' ) ) {
			delete_option( '_wpghs_export_user_id' );
		} else {
			$user_id = get_current_user_id();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			// @todo is this what we want to include here?
			return array(
				'name'  => 'Anonymous',
				'email' => 'anonymous@users.noreply.github.com',
			);
		}

		return array(
			'name'  => $user->display_name,
			'email' => $user->user_email,
		);
	}
}
