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

    $this->api = new WordPress_GitHub_Sync_Api;

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
    preg_match("/_posts\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.md/", $this->path, $matches);
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
   * Combines the 2 content parts for GitHub
   */
  function github_content() {
    return $this->front_matter() . $this->post_content();
  }

  /**
   * The post's YAML frontmatter
   *
   * Returns String the YAML frontmatter, ready to be written to the file
   */
  function front_matter() {
    return Spyc::YAMLDump($this->meta(), false, false, true) . "---\n";
  }

  /**
   * Returns the post_content
   *
   * Markdownify's the content if applicable
   */
  function post_content() {
    $content = $this->post->post_content;

    if ( function_exists( 'wpmarkdown_html_to_markdown' ) ) {
      $content = wpmarkdown_html_to_markdown( $content );
    }

    return apply_filters( 'wpghs_content', $content );
  }

  /**
   * Retrieves or calculates the proper GitHub path for a given post
   *
   * Returns (string) the path relative to repo root
   */
  function github_path() {
    return $this->github_folder() . $this->github_filename();
  }

  /**
   * Get GitHub folder based on post
   */
  function github_folder() {
    $folder = "";

    if ($this->type() == "post") {
      $folder = "_posts/";
    }

    return $folder;
  }

  /**
   * Build GitHub filename based on post
   */
  function github_filename() {
    $filename = "";

    if ($this->type() == "post") {
      $filename = get_the_time("Y-m-d-", $this->id) . $this->name() . ".md";
    } elseif ($this->type() == "page") {
      $filename = get_page_uri( $this->id ) . ".md";
    }

    return $filename;
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
   * The post's sha
   * Cached as post meta, or will make a live call if need be
   *
   * Returns String the sha1 hash
   */
  function sha() {
    $sha = get_post_meta( $this->id, "_sha", true);

    // If we've done a full export and we have no sha
    // then we should try a live check to see if it exists
    if ( ! $sha && 'yes' === get_option( '_wpghs_fully_exported' ) ) {
      $data = $this->api->remote_contents($this);

      if ($data && isset($data->sha)) {
        add_post_meta( $this->id, '_sha', $data->sha, true ) || update_post_meta( $this->id, '_sha', $data->sha );
        $sha = $data->sha;
      }
    }

    // if the sha still doesn't exist, then it's empty
    if ( ! $sha ) {
      $sha = "";
    }

    return $sha;
  }

  /**
   * Save the sha to post
   */
  function set_sha($sha) {
    add_post_meta( $this->id, '_sha', $sha, true ) || update_post_meta( $this->id, '_sha', $sha );
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
      'post_date'    => $this->post->post_date,
      'post_excerpt' => $this->post->post_excerpt,
      'layout'       => get_post_type( $this->post ),
      'permalink'    => get_permalink( $this->post )
    );

    //convert traditional post_meta values, hide hidden values
    foreach ( get_post_custom( $this->id ) as $key => $value ) {

      if ( substr( $key, 0, 1 ) == '_' )
        continue;

      $meta[ $key ] = $value;

    }

    return $meta;

  }
}
