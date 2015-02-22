<?php
/**
 * Interfaces with the GitHub API
 */
class WordPress_GitHub_Sync_Api {

  /**
   * Retrieves the blob data for a given sha
   */
  function get_blob($sha) {
    if (! $this->oauth_token() || ! $this->repository()) {
      return false;
    }

    return $this->call("GET", $this->blob_endpoint() . "/" . $sha);
  }

  /**
   * Retrieves a tree by sha recursively from the GitHub API
   */
  function get_tree_recursive($sha) {
    if (! $this->oauth_token() || ! $this->repository()) {
      return false;
    }

    $data = $this->call("GET", $this->tree_endpoint() . "/" . $sha . "?recursive=1");

    foreach ($data->tree as $index => $thing) {
      // We need to remove the trees because
      // the recursive tree includes both
      // the subtrees as well the subtrees' blobs
      if ( $thing->type === "tree" ) {
        unset($data->tree[ $index ]);
      }
    }

    return array_values($data->tree);
  }

  /**
   * Retrieves a commit by sha from the GitHub API
   */
  function get_commit($sha) {
    if (! $this->oauth_token() || ! $this->repository()) {
      return false;
    }

    return $this->call("GET", $this->commit_endpoint() . "/" . $sha);
  }

  /**
   * Retrieves the current master branch
   */
  function get_ref_master() {
    if (! $this->oauth_token() || ! $this->repository()) {
      return false;
    }

    return $this->call("GET", $this->reference_endpoint());
  }

  /**
   * Create the tree by a set of blob ids
   */
  function create_tree($tree) {
    $body = array( 'tree' => $tree );

    return $this->call("POST", $this->tree_endpoint(), $body);
  }

  /**
   * Create the commit from tree sha
   *
   * $sha - string   shasum for the tree for this commit
   */
  function create_commit($sha, $msg) {
    $parent_sha = $this->last_commit_sha();

    if ( is_wp_error( $parent_sha ) ) {
      return $parent_sha;
    }

    $body = array(
      "message" => $msg,
      "author"  => $this->export_user(),
      "tree"    => $sha,
      "parents" => array( $parent_sha ),
    );

    return $this->call("POST", $this->commit_endpoint(), $body);
  }

  /**
   * Updates the master branch to point to the new commit
   *
   * $sha - string   shasum for the commit for the master branch
   */
  function set_ref($sha) {
    $body = array(
      'sha' => $sha,
    );

    return $this->call("POST", $this->reference_endpoint(), $body);
  }

  /**
   * Retrieves the recursive tree for the master branch
   */
  function last_tree_recursive() {
    $sha = $this->last_tree_sha();

    if ( is_wp_error( $sha ) ) {
      return $sha;
    }

    return $this->get_tree_recursive( $sha );
  }

  /**
   * Retrieves the sha for the last tree
   */
  function last_tree_sha() {
    $data = $this->last_commit();

    if ( is_wp_error( $data ) ) {
      return $data;
    }

    return $data->tree->sha;
  }

  /**
   * Retrieve the last commit in the repository
   */
  function last_commit() {
    $sha = $this->last_commit_sha();

    if ( is_wp_error( $sha ) ) {
      return $data;
    }

    return $this->get_commit( $sha );
  }

  /**
   * Retrieve the sha for the latest commit
   */
  function last_commit_sha() {
    $data = $this->get_ref_master();

    if ( is_wp_error( $data ) ) {
      return $data;
    }

    return $data->object->sha;
  }

  /**
   * Calls the content API to get the post's contents and metadata
   *
   * Returns Object the response from the API
   */
  function remote_contents($post) {
    return $this->call("GET", $this->content_endpoint() . $post->github_path());
  }

  /**
   * Generic GitHub API interface and response handler
   */
  function call($method, $endpoint, $body = array()) {
    $args = array(
      "method"  => $method,
      "headers" => array(
        "Authorization" => "token " . $this->oauth_token()
      ),
      "body"    => json_encode($body)
    );

    $response = wp_remote_request( $endpoint, $args );
    $status = wp_remote_retrieve_header( $response, 'status' );
    $body = json_decode(wp_remote_retrieve_body( $response ) );

    if ( "2" !== substr($status, 0, 1) && "3" !== substr($status, 0, 1)  ) {
      return new WP_Error( $status, $body->message );
    }

    return $body;
  }

  /**
   * Get the data for the current user
   */
  function export_user() {
    if ( $user_id = get_option( '_wpghs_export_user_id' ) ) {
      delete_option( '_wpghs_export_user_id' );
    } else {
      $user_id = get_current_user_id();
    }

    $user = get_userdata($user_id);

    if (!$user) {
      return array();
    }

    return array(
      'name'  => $user->display_name,
      'email' => $user->user_email,
    );
  }

  /**
   * Returns the repository to sync with
   */
  function repository() {
    return get_option( "wpghs_repository" );
  }

  /**
   * Returns the user's oauth token
   */
  function oauth_token() {
    return get_option( "wpghs_oauth_token" );
  }

  /**
   * Returns the GitHub host to sync with (for GitHub Enterprise support)
   */
  function api_base() {
    return get_option( "wpghs_host" );
  }

  /**
   * API endpoint for the master branch reference
   */
  function reference_endpoint() {
    $url = $this->api_base() . "/repos/";
    $url = $url . $this->repository() . "/git/refs/heads/master";

    return $url;
  }

  /**
   * Api to get and create commits
   */
  function commit_endpoint() {
    $url = $this->api_base() . "/repos/";
    $url = $url . $this->repository() . "/git/commits";

    return $url;
  }

  /**
   * Api to get and create trees
   */
  function tree_endpoint() {
    $url = $this->api_base() . "/repos/";
    $url = $url . $this->repository() . "/git/trees";

    return $url;
  }

  /**
   * Builds the proper blob API endpoint for a given post
   *
   * Returns String the relative API call path
   */
  function blob_endpoint() {
    $url = $this->api_base() . "/repos/";
    $url = $url . $this->repository() . "/git/blobs";

    return $url;
  }

  /**
   * Builds the proper content API endpoint for a given post
   *
   * Returns String the relative API call path
   */
  function content_endpoint() {
    $url = $this->api_base() . "/repos/";
    $url = $url . $this->repository() . "/contents/";

    return $url;
  }
}