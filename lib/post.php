<?php
/**
 * The post object which represents both the GitHub and WordPress post
 */
class WordPress_GitHub_Sync_Post {
  public $id = 0;

  /**
   * Instantiates a new Post object
   *
   * $id_or_path - (int|string) either a postID (WordPress) or a path to a file (GitHub)
   *
   * Returns the Post object, duh
   */
  function __construct( $id_or_path ) {

    if (is_numeric($id_or_path)) {
      $this->id = $id_or_path;
    } else {
      $this->path = $id_or_path;
      $this->id = $this->id_from_path();
    }

    $this->post = get_post($this->id);
  }

  /**
   * Parse the various parts of a filename from a path
   *
   * @todo - PAGE SUPPORT
   */
  function parts_from_path() {
    preg_match("/_posts\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.html/", $this->path, $matches);
    return $matches;
  }

  /**
   * Extract's the post's title from its path
   */
  function title_from_path() {
    $matches = $this->parts_from_path();
    return $matches[4];
  }

  /**
   * Extract's the post's date from its path
   */
  function date_from_path() {
    $matches = $this->parts_from_path();
    return $matches[1] . "-" . $matches[2] . "-" . $matches[3] . "00:00:00";
  }

  /**
   * Determines the post's WordPress ID from its GitHub path
   * Creates the WordPress post if it does not exist
   */
  function id_from_path() {
    global $wpdb;

    $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wpghs_github_path' AND meta_value = '$this->path'");

    if (!$id) {
      $title = $this->title_from_path();
      $id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$title'");
    }

    if (!$id) {
      $id = wp_insert_post( array(
          'post_name' => $this->title_from_path(),
          'post_date' => $this->date_from_path()
        )
      );
    }

    return $id;
  }

  /**
   * Returns the post type
   */
  function type() {
    return $this->post->post_type;
  }

  /**
   * Returns the post name
   */
  function name() {
    return $this->post->post_name;
  }

  /**
   * Retrieves or calculates the proper GitHub path for a given post
   *
   * Returns (string) the path relative to repo root
   */
  function github_path() {
    $path = get_post_meta( $this->id, '_wpghs_github_path', true );

    if ( ! $path ) {
      if ($this->type() == "post") {
        $path = "_posts/";
        $path = $path . get_the_time("Y-m-d-", $this->id);
        $path = $path . $this->name() . ".md";
      } elseif ($this->type() == "page") {
        $path = get_page_uri( $this->id ) . ".md";
      }

      update_post_meta( $this->id, '_wpghs_github_path', $path );
    }
    return $path;
  }

  /**
   * Determines the last author to modify the post
   *
   * Returns Array an array containing the author name and email
   */
  function last_modified_author() {
    if ( $last_id = get_post_meta( $this->id, '_edit_last', true) ) {
      $user = get_userdata($last_id);
      if (!$user) return array();
      return array( "name" => $user->display_name, "email" => $user->user_email );
    } else {
      return array();
    }
  }

  /**
   * Builds the proper content API endpoint for a given post
   *
   * Returns String the relative API call path
   */
  function api_endpoint() {
    global $wpghs;
    $url = $wpghs->api_base() . "/repos/";
    $url = $url . $wpghs->repository() . "/contents/";
    $url = $url . $this->github_path();
    return $url;
  }

  /**
   * Calls the content API to get the post's contents and metadata
   *
   * Returns Object the response from the API
   */
  function remote_contents() {
    global $wpghs;
    $response = wp_remote_get( $this->api_endpoint(), array(
      "headers" => array(
        "Authorization" => "token " . $wpghs->oauth_token()
        )
      )
    );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    return $data;
  }

  /**
   * The post's sha
   * Cached as post meta, or will make a live call if need be
   *
   * Returns String the sha1 hash
   */
  function sha() {
    if ($sha = get_post_meta( $this->id, "_sha", true)) {
      return $sha;
    } else {
      $data = $this->remote_contents();
      if ($data && isset($data->sha)) {
        add_post_meta( $this->id, '_sha', $data->sha, true ) || update_post_meta( $this->id, '_sha', $data->sha );
        return $data->sha;
      } else {
        return "";
      }
    }
  }

  /**
   * Push the post to GitHub
   */
  function push() {
    global $wpghs;

    if ($wpghs->push_lock)
      return false;

    $args = array(
      "method"  => "PUT",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( array(
          "message" => "Syncing " . $this->github_path() . " from WordPress",
          "content" => base64_encode($this->front_matter() . $this->post->post_content),
          "author"  => $this->last_modified_author(),
          "sha"     => $this->sha()
        ) )
    );

    $response = wp_remote_request( $this->api_endpoint(), $args );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data && isset($data->content) && !isset($data->errors)) {
      $sha = $data->content->sha;
      add_post_meta( $this->id, '_sha', $sha, true ) || update_post_meta( $this->id, '_sha', $sha );
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
  function pull() {
    global $wpghs;

    $data = $this->remote_contents();
    $content = base64_decode($data->content);

    // Break out meta, if present
    preg_match( "/(^---(.*)---$)?(.*)/ms", $content, $matches );
    $body = array_pop( $matches );

    if ( count($matches) == 3) {
      $meta = spyc_load($matches[2]);
    } else {
      $meta = array();
    }

    wp_update_post( array_merge( $meta, array(
        "ID"           => $this->id,
        "post_content" => $body
      ))
    );
  }

  /**
   * Delete a post from GitHub
   */
  function delete() {
    global $wpghs;

    if ($wpghs->push_lock)
      return false;

    $args = array(
      "method"  => "DELETE",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( array(
          "message" => "Deleting " . $this->github_path() . " via WordPress",
          "author"  => $this->last_modified_author(),
          "sha"     => $this->sha()
        ) )
    );

    wp_remote_request( $this->api_endpoint(), $args );
  }

  /**
   * The post's metadata
   *
   * Returns Array the post's metadata
   */
  function meta() {

    $meta = array(
      'post_title'   => get_the_title( $this->post ),
      'author'       => get_userdata( $this->post->post_author )->display_name,
      'post_excerpt' => $this->post->post_excerpt,
      'layout'       => get_post_type( $this->post ),
      'permalink'    => str_replace( home_url(), '', get_permalink( $this->post ) )
    );

    //convert traditional post_meta values, hide hidden values
    foreach ( get_post_custom( $this->id ) as $key => $value ) {

      if ( substr( $key, 0, 1 ) == '_' )
        continue;

      $meta[ $key ] = $value;

    }

    return $meta;

  }

  /**
   * The post's YAML frontmatter
   *
   * Returns String the YAML frontmatter, ready to be written to the file
   */
  function front_matter() {
    return Spyc::YAMLDump($this->meta(), false, false, true) . "---\n";
  }
}
