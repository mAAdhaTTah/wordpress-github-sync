<?php
class WordPress_GitHub_Sync_Post {
  public $id = 0;

  function __construct( $id_or_path ) {

    if (is_numeric($id_or_path)) {
      $this->id = $id_or_path;
    } else {
      $this->path = $id_or_path;
      $this->id = $this->id_from_path();
    }

    $this->post = get_post($this->id);
  }

  function parts_from_path() {
    preg_match("/_posts\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.html/", $this->path, $matches);
    return $matches;
  }

  function title_from_path() {
    $matches = $this->parts_from_path();
    return $matches[4];
  }

  function date_from_path() {
    $matches = $this->parts_from_path();
    return $matches[1] . "-" . $matches[2] . "-" . $matches[3] . "00:00:00";
  }

  function id_from_path() {
    global $wpdb;
    $title = $this->title_from_path();
    $id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$title'");
    if (!$id) {
      $id = wp_insert_post( array(
          'post_name' => $this->title_from_path(),
          'post_date' => $this->date_from_path()
        )
      );
    }
    return $id;
  }

  function type() {
    if ($this->id == 0) {
      return "github";
    } else {
      return $this->post->post_type;
    }
  }

  function name() {
    return $this->post->post_name;
  }

  function github_path() {
    if ($this->type() == "post") {
      $path = "_posts/";
      $path = $path . get_the_time("Y-m-d-", $this->id);
      $path = $path . $this->name() . ".html";
    } elseif ($this->type() == "page") {
      $path = get_page_uri( $this->id ) . ".html";
    }
    return $path;
  }

  function last_modified_author() {
    if ( $last_id = get_post_meta( $this->id, '_edit_last', true) ) {
      $user = get_userdata($last_id);
      return array( "name" => $user->display_name, "email" => $user->user_email );
    } else {
      return array();
    }
  }

  function api_endpoint() {
    global $wpghs;
    $url = $wpghs->api_base() . "/repos/";
    $url = $url . $wpghs->repository() . "/contents/";
    $url = $url . $this->github_path();
    return $url;
  }

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

  function sha() {
    if ($sha = get_post_meta( $this->id, "_sha", true)) {
      return $sha;
    } else {
      $data = $this->remote_contents();
      if ($data && isset($data->sha)) {
        return $data->sha;
      } else {
        return "";
      }
    }
  }

  function push() {
    global $wpghs;

    $args = array(
      "method"  => "PUT",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( array(
          "message" => "Syncing " . $this->github_path() . " from WordPress",
          "content" => base64_encode($this->post->post_content),
          "author"  => $this->last_modified_author(),
          "sha"     => $this->sha()
        ) )
    );

    $response = wp_remote_request( $this->api_endpoint(), $args );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    if ($body && $data && !isset($data->errors)) {
      $sha = $data->content->sha;
      add_post_meta( $this->id, '_sha', $sha, true ) || update_post_meta( $this->id, '_sha', $sha );
    } else {
      wp_die( __("WordPress <--> GitHub sync error: ", WordPress_GitHub_Sync::$text_domain) . $data->message );
    }
  }

  function pull() {
    global $wpghs;
    $data = $this->remote_contents();
    remove_action( 'save_post', array( &$wpghs, 'push_post' ) );
    wp_update_post( array(
        "ID"           => $this->id,
        "post_content" => base64_decode($data->content)
      )
    );
    add_action( 'save_post', array( &$wpghs, 'push_post' ) );
  }

  function delete() {
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
}
