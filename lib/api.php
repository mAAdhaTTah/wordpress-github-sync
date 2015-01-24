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

    $blob = $this->call("GET", $this->blob_endpoint() . "/" . $sha);

    return $blob;
  }

  /**
   * Create the tree by a set of blob ids
   */
  function create_tree($tree) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $body = array( 'tree' => $tree );
    $data = $this->call("POST", $this->tree_endpoint(), $body);

    if ($data && isset($data->sha) && !isset($data->errors)) {
      update_option( '_wpghs_last_tree_sha', $data->sha );
      return $data;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Create the commit from tree sha
   *
   * $sha - string   shasum for the tree for this commit
   */
  function create_commit($sha) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $body = array(
      "message" => "Full export from WordPress at " . site_url() . " (" . get_bloginfo( 'name' ) . ")",
      "author"  => $this->export_user(),
      "tree"    => $sha,
      "parents" => array( $this->last_commit_sha() ),
    );

    $data = $this->call("POST", $this->commit_endpoint(), $body);

    if ($data && isset($data->sha) && !isset($data->errors)) {
      return $data->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Updates the master branch to point to the new commit
   *
   * $sha - string   shasum for the commit for the master branch
   */
  function set_ref($sha) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $body = array(
      'sha' => $sha,
    );

    $data = $this->call("POST", $this->reference_endpoint(), $body);

    if ($data && isset($data->object) && !isset($data->errors)) {
      update_option( '_wpghs_last_commit_sha', $data->object->sha );
      return $data->object->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Push the post to GitHub
   */
  function push($post) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $body = array(
      "message" => "Syncing " . $post->github_path() . " from WordPress at " . site_url() . " (" . get_bloginfo( 'name' ) . ")",
      "content" => base64_encode($post->github_content()),
      "author"  => $post->last_modified_author(),
      "sha"     => $post->sha()
    );

    $data = $this->call("PUT", $this->content_endpoint() . $post->github_path(), $body );

    if ($data && isset($data->content) && !isset($data->errors)) {
      $sha = $data->content->sha;
      add_post_meta( $post->id, '_sha', $sha, true ) || update_post_meta( $post->id, '_sha', $sha );
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }

    return true;
  }

  /**
   * Pull the post from GitHub
   */
  function pull($post) {
    $data = $this->remote_contents($post);
    $content = base64_decode($data->content);

    // Break out meta, if present
    preg_match( "/(^---(.*)---$)?(.*)/ms", $content, $matches );

    $body = array_pop( $matches );

    if (count($matches) == 3) {
      $meta = spyc_load($matches[2]);
      if ($meta['permalink']) $meta['permalink'] = str_replace(home_url(), '', get_permalink($meta['permalink']));
    } else {
      $meta = array();
    }

    if ( function_exists( 'wpmarkdown_markdown_to_html' ) ) {
      $body = wpmarkdown_markdown_to_html( $body );
    }

    wp_update_post( array_merge( $meta, array(
        "ID"           => $post->id,
        "post_content" => $body
      ))
    );
  }

  /**
   * Delete a post from GitHub
   */
  function delete($post) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $body = array(
      "message" => "Deleting " . $post->github_path() . " via WordPress at " . site_url() . " (" . get_bloginfo( 'name' ) . ")",
      "author"  => $post->last_modified_author(),
      "sha"     => $post->sha()
    );

    $this->call("DELETE", $this->content_endpoint() . $post->github_path(), $body);
  }

  /**
   * Retrieves the recursive tree for the master branch
   */
  function last_tree_recursive() {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $data = $this->call("GET", $this->tree_endpoint() . "/" . $this->last_tree_sha() . "?recursive=1");

    return $data->tree;
  }

  /**
   * Retrieves the sha for the last tree
   *
   * Makes a live call if not saved
   */
  function last_tree_sha() {
    global $wpghs;

    $sha = get_option( "_wpghs_last_tree_sha" );

    if ( !empty($sha) ) {
      return $sha;
    }

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $data = $this->call("GET", $this->commit_endpoint() . "/" . $this->last_commit_sha() );

    if ($data && isset($data->tree) && !isset($data->errors)) {
      update_option( "_wpghs_last_tree_sha", $data->tree->sha );
      return $data->tree->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Retrieve the sha for the latest commit
   *
   * Will make a live call if not found
   */
  function last_commit_sha() {
    global $wpghs;

    $sha = get_option( "_wpghs_last_commit_sha" );

    if ( !empty($sha) ) {
      return $sha;
    }

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    $data = $this->call("GET", $this->reference_endpoint());
    $sha = $data->object->sha;

    update_option( "_wpghs_last_commit_sha", $sha );
    return $sha;
  }

  /**
   * Calls the content API to get the post's contents and metadata
   *
   * Returns Object the response from the API
   */
  function remote_contents($post) {
    global $wpghs;

    if (! $this->oauth_token() || ! $this->repository() || $wpghs->push_lock) {
      return false;
    }

    return $this->call("GET", $this->content_endpoint() . $post->github_path());
  }

  /**
   * Generic GitHub API interface and response handler
   *
   * @todo Error handle the data response
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

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    return $data;
  }

  /**
   * Get the data for the current user
   *
   * @todo check if object, set some defaults if not
   */
  function export_user() {
    $user_id = get_option( '_wpghs_export_user_id' );
    delete_option( '_wpghs_export_user_id' );

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
   * Api to update the master branch's reference
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