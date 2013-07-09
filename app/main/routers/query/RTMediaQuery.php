<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RTMediaQuery
 *
 * @author saurabh
 */
class RTMediaQuery {

	/**
	 *
	 * @var array The query arguments for the current instance
	 */
	public $query = '';

	/**
	 *
	 * @var array The query arguments for the current instance (variable)
	 */
	public $media_query = '';

	/**
	 *
	 * @var object The current action object (edit/delete/custom)
	 */
	public $action_query = false;

	/**
	 *
	 * @var object The currently relevant interaction object
	 */
	private $interaction;

	/**
	 *
	 * @var array The actions recognised for the object
	 */
	public $actions = array(
		'edit' => array( 'Edit', false ),
		'delete' => array( 'Delete', false ),
		'comment' => array( 'Comment', true )
	);
	public $media = '';
	public $media_count = 0;
	public $current_media = -1;
	public $in_the_media_loop = false;
	public $format = false;

	/**
	 * Initialise the query
	 *
	 * @global object $rtmedia_interaction The global interaction object
	 * @param array $args The query arguments
	 */
	function __construct( $args = false ) {

		// set up the interaction object relevant to just the query
		// we only need information related to the media route
		global $rtmedia_interaction;

		$this->model = new RTMediaModel();


		$this->interaction = $rtmedia_interaction->routes[ 'media' ];

		$this->friendship = new RTMediaFriends();



		// action manipulator hook
		$this->set_actions();

		//check and set the format to json, if needed
		$this->set_json_format();

		// set up the action query from the URL
		$this->set_action_query();

		add_filter( 'rtmedia-model-where-query', array( $this, 'privacy_filter' ),1,2 );

		// if no args were supplied, initialise the $args
		if ( empty( $args ) ) {

			$this->init();

			// otherwise just populate the query
		} else {

			$this->query( $args );
		}

		do_action('rtmedia_query_construct');

	}

	/**
	 * Initialise the default args for the query
	 */
	function init() {

	}

	function set_media_type() {
		if ( ! isset( $this->query[ 'media_type' ] ) ) {
			if ( isset( $this->action_query->id ) ) {
				$media = $this->model->get( array( 'id' => $this->action_query->id ) );
				$media_type = $media[ 0 ]->media_type;
				$this->query[ 'media_type' ] = $media_type;
			}
		}
	}

	function is_single() {
		/**
		 * // check the condition
		 * wont be true in case of multiple albums
		 */
		if ( ! isset( $this->action_query->id ) || $this->is_album() ) {
			return false;
		} else {
			if ( isset( $this->query[ 'media_type' ] ) &&
					$this->query[ 'media_type' ] == 'album' ) {
				return false;
			}
		}

		return true;
	}

	function is_album() {
		if ( isset( $this->query[ 'media_type' ] ) && $this->query[ 'media_type' ] == 'album' ) {
			return true;
		}
		return false;
	}

	function is_gallery() {
		if ( ! $this->is_single() )
			return true;

		return false;
	}

	function is_album_gallery() {
		if ( isset( $this->action_query->media_type ) && $this->action_query->media_type == 'album' ) {
			return true;
		}
		return false;
	}

	/**
	 * json request
	 */
	function set_json_format() {

		if ( isset( $_GET[ 'json' ] ) || isset( $_POST[ 'json' ] ) ) {
			$this->format = 'json';
		}
	}

	function set_action_query() {

		$raw_query = $this->interaction->query_vars;


		$bulk = false;
		$action = false;
		$attribute = false;
		$modifier_type = 'default';
		$modifier_value = false;
		$format = '';
		$pageno = 1;
		$attributes = '';



		// The first part of the query /media/{*}/
		if ( is_array( $raw_query ) &&
				count( $raw_query ) &&
				! empty( $raw_query[ 0 ] ) ) {

			//set the modifier value beforehand
			$modifier_value = $raw_query[ 0 ];

			// requesting nonce /media/nonce/edit/ | /media/nonce/comment
			// | /media/nonce/delete

			if ( $modifier_value == 'nonce' ) {

				$modifier_type = 'nonce';

				// requesting media id /media/{id}/
			} elseif ( is_numeric( $modifier_value ) ) {

				$modifier_type = 'id';

				// this block is unnecessary, please delete, asap
				if ( isset( $_POST[ 'request_action' ] ) &&
						$_POST[ 'request_action' ] == 'delete' ) {

					$action = 'delete';
				}

				// requesting an upload screen /media/upload/
			} elseif ( array_key_exists( $modifier_value, $this->actions ) ) {
				// /media/edit/ | media/delete/ | /media/like/

				$action = $modifier_value;
				$bulk = true;
			} elseif ( $modifier_value == 'upload' ) {

				$modifier_type = 'upload';
				$action = 'upload';

				// /media/pg/2/
			} elseif ( $modifier_value == 'pg' ) {

				//paginating default query
				$modifier_type = 'pg';
			} else {

				// requesting by media type /media/photos | /media/videos/
				$modifier_type = 'media_type';
			}
		}



		if ( isset( $raw_query[ 1 ] ) ) {

			$second_modifier = $raw_query[ 1 ];


			switch ( $modifier_type ) {

				case 'nonce':

					// /media/nonce/edit/ | /media/nonce/delete/
					if ( array_key_exists( $second_modifier, $this->actions ) ) {

						$nonce_type = $second_modifier;
					}

					break;

				case 'id':

					// /media/23/edit/ | media/23/delete/ | /media/23/like/
					if ( array_key_exists( $second_modifier, $this->actions ) ) {

						$action = $second_modifier;
					}
					break;

				case 'pg':

					// /media/page/2/ | /media/page/3/
					if ( is_numeric( $second_modifier ) ) {

						$pageno = $second_modifier;
					}
					break;

				case 'media_type':

					// /media/photos/edit/ | /media/videos/edit/
					if ( array_key_exists( $second_modifier, $this->actions ) ) {

						$action = $second_modifier;
						$bulk = true;
					}
					// /media/photos/page/2/
					//elseif($second_modifier=='page'){
					//$page = $second_modifier;
					//pagination support
					//}
					break;

				default:
					break;
			}
		}

		//the third part of the query /media/modifier/second_modifier/{*}

		if ( isset( $raw_query[ 2 ] ) ) {

			$third_modifier = $raw_query[ 2 ];

			switch ( $modifier_type ) {

				case 'nonce':

					// leaving here for more granular nonce, in future, for eg,
					// /media/nonce/edit/title/

					break;

				case 'id':

					// leaving here for more granular editing, in future, for eg,
					// /media/23/edit/title/

					break;

				case 'media_type':

					// /media/photos/edit/ | /media/videos/edit/
					// leaving here for more granular editing, in future, for eg,
					// /media/photos/edit/title/
					// /media/photos/page/2/
					if ( $second_modifier == 'pg' && is_numeric( $third_modifier ) ) {

						$pageno = $third_modifier;
					}
					break;

				case 'pg':
				default:
					break;
			}
		}


		global $rtmedia;

		//if ( ! $rtmedia->get_option( 'media_end_point_enable' ) )
		//include get_404_template();

		/**
		 * set action query object
		 * setting parameters in action query object for pagination
		 */
		$per_page_media = intval( $rtmedia->options[ 'general_perPageMedia' ] );


		$this->action_query = (object) array(
					$modifier_type => $modifier_value,
					'action' => $action,
					'bulk' => $bulk,
					'page' => $pageno,
					'per_page_media' => $per_page_media,
					'attributes' => $attributes
		);
	}

	/**
	 * additional actions to be added via action hook
	 */
	function set_actions() {
		$this->actions = apply_filters( 'rtmedia_query_actions', $this->actions );
	}

	/**
	 * get media query for the request
	 * @param type $query
	 * @return type
	 */
	function &query( $query ) {
		$this->query = wp_parse_args($query , $this->query );
		$this->set_media_type();
		$this->media_query = $this->query;
		return $this->get_data();
	}

	function privacy_filter( $where,$table_name ) {
		$user = $this->get_user();

		$where .= " AND ({$table_name}.privacy is NULL OR {$table_name}.privacy=0";
		if ( $user ) {
			$where .= " OR ({$table_name}.privacy=20)";
			$where .= " OR ({$table_name}.media_author={$user} AND {$table_name}.privacy>=40)";
			if ( class_exists( 'BuddyPress' ) ) {
				if ( bp_is_active( 'friends' ) ) {
					$friends = $this->friendship->get_friends_cache( $user );
					$where .= " OR ({$table_name}.privacy=40 AND {$table_name}.media_author IN ('". implode("','", $friends)."'))";
				}
			}
		}
		return $where . ')';
	}

	function get_user() {
		if ( is_user_logged_in() ) {
			$user = get_current_user_id();
		} else {
			$user = 0;
		}
		return $user;
	}



	function set_privacy() {
		$user = $this->get_user();
		if ( ! $user ) {
			$privacy = 0;
		} else {
			$privacy = 20;
		}
	}

	function populate_media() {

		$this->set_privacy();
		if ( $this->is_single() )
			$this->media_query[ 'id' ] = $this->action_query->id;

		$allowed_media_types = array( );

		// is this an album or some other media
		$this->album_or_media();

		$order_by = $this->order_by();

		if ( isset( $this->media_query[ 'context' ] ) ) {
			if ( $this->media_query[ 'context' ] == 'profile' ) {

				if ( ! $this->is_album_gallery() )
					$this->media_query[ 'media_author' ] = $this->media_query[ 'context_id' ];
				else
					$author = $this->media_query[ 'context_id' ];

				unset( $this->media_query[ 'context' ] );
				unset( $this->media_query[ 'context_id' ] );
			} else if ( $this->media_query[ 'context' ] == 'group' ) {
                                $group_id = $this->media_query[ 'context_id' ];
			} else {

			}
		}


		if ( $this->is_album_gallery() ) {
                
                        if ( isset($author) ) {
                            $query_function = 'get_user_albums';
                            $context_id = $author;
                        } elseif( isset($group_id) ) {
                            $query_function = 'get_group_albums';
                            $context_id = $group_id;
                        }
                        
			if ( $order_by == ' ' )
				$pre_media = $this->model->$query_function( $context_id, ($this->action_query->page - 1) * $this->action_query->per_page_media, $this->action_query->per_page_media );
			else
				$pre_media = $this->model->$query_function( $context_id, ($this->action_query->page - 1) * $this->action_query->per_page_media, $this->action_query->per_page_media, $order_by );

			$media_for_total_count = $this->model->$query_function( $context_id, false, false );
		} else {
			/**
			 * fetch media entries from rtMedia context
			 */
			if ( $order_by == ' ' )
				$pre_media = $this->model->get_media( $this->media_query, ($this->action_query->page - 1) * $this->action_query->per_page_media, $this->action_query->per_page_media );
			else
				$pre_media = $this->model->get_media( $this->media_query, ($this->action_query->page - 1) * $this->action_query->per_page_media, $this->action_query->per_page_media, $order_by );

			/**
			 * count total medias in album irrespective of pagination
			 */
			$media_for_total_count = $this->model->get_media( $this->media_query, false, false );
		}

		$this->media_count = count( $media_for_total_count );

		if ( ! $pre_media )
			return false;
		else
			return $pre_media;

		/* 		removed because of indexing ---- 0,1,2 was required rather than post_ids
		  foreach ( $pre_media as $pre_medium ) {
		  $this->media[ $pre_medium->media_id ] = $pre_medium;
		  } */
	}

	function album_or_media(){
		global $rtmedia;
		foreach ( $rtmedia->allowed_types as $value ) {
			$allowed_media_types[ ] = $value[ 'name' ];
		}

		if ( ! isset( $this->media_query[ 'media_type' ] ) ) {
			if ( isset( $this->action_query->media_type ) &&
					(
					in_array( $this->action_query->media_type, $allowed_media_types ) ||
					$this->action_query->media_type == 'album'
					)
			) {
				$this->media_query[ 'media_type' ] = $this->action_query->media_type;
			} else {
				$this->media_query[ 'media_type' ] = array( 'compare' => 'NOT IN', 'value' => array( 'album' ) );
			}
		}
	}

	function order_by(){
		/**
		 * Handle order of the result set
		 */
		$order_by = '';
		$order = '';
		if ( isset( $this->media_query[ 'order' ] ) ) {
			$order = $this->media_query[ 'order' ];
			unset( $this->media_query[ 'order' ] );
		}

		if ( isset( $this->media_query[ 'order_by' ] ) ) {
			$order_by = $this->media_query[ 'order_by' ];
			unset( $this->media_query[ 'order_by' ] );
			if ( $order_by == 'ratings' )
				$order_by = 'ratings_average ' . $order . ', ratings_count';
		}
		$order_by .= ' ' . $order;

		return $order_by = apply_filters('rtmedia_model_order_by', $order_by);
	}

	function populate_album() {
		$this->album = $this->media;
		$this->media_query[ 'album_id' ] = $this->action_query->id;
		unset( $this->action_query->id );
		unset( $this->media_query[ 'id' ] );
		unset( $this->media_query[ 'media_type' ] );
		return $this->populate_media();
	}

	function populate_comments() {

		$this->model = new RTMediaCommentModel();
		global $rtmedia_interaction;

		return $this->model->get( array( 'post_id' => $rtmedia_interaction->context->id ) );
	}

	/**
	 * populate the data object for the page/album
	 *
	 * @return boolean
	 */
	function populate_data() {
		unset( $this->media_query->meta_query );
		unset( $this->media_query->tax_query );

		if ( $this->action_query->action == 'comments' && ! isset( $this->action_query->id ) )
			$this->media = $this->populate_comments();
		else
			$this->media = $this->populate_media();

		if ( $this->is_album() ) {
			$this->media = $this->populate_album();
		}

		if ( empty( $this->media ) )
			return;

		/**
		 * multiside manipulation
		 */
		if ( is_multisite() ) {
			$blogs = array( );
			foreach ( $this->media as $media ) {
				$blogs[ $media->blog_id ][ ] = $media;
			}


			foreach ( $blogs as $blog_id => &$media ) {
				switch_to_blog( $blog_id );
				if ( ! ($this->action_query->action == 'comments' && ! isset( $this->action_query->id )) ) {
					$this->populate_post_data( $media );
					wp_reset_query();
				}
			}
			restore_current_blog();
		} else {
			if ( ! ($this->action_query->action == 'comments' && ! isset( $this->action_query->id )) )
				$this->populate_post_data( $this->media );
		}
	}

	/**
	 * helper method to fetch media id of each media from the map
	 * @param type $media
	 * @return type
	 */
	function get_media_id( $media ) {
		return $media->media_id;
	}

	/**
	 * helper method to find the array entry for the given media id
	 * @param type $id
	 * @return null
	 */
	function get_media_by_media_id( $id ) {

		foreach ( $this->media as $key => $media ) {
			if ( $media->media_id == $id )
				return $key;
		}
		return null;
	}

	/**
	 * populate the post data for the fetched media from rtMedia context
	 * @param type $media
	 */
	function populate_post_data( $media ) {
		if ( ! empty( $media ) && is_array( $media ) ) {

			/**
			 * setting up query vars for WP_Query
			 */
			$media_post_query_args = array(
				'orderby' => 'ID',
				'order' => 'DESC',
				'post_type' => 'any',
				'post_status' => 'any',
				'post__in' => array_map( array( $this, 'get_media_id' ), $media ),
				'ignore_sticky_posts' => 1,
				'posts_per_page' => $this->action_query->per_page_media
			);

			/**
			 * setting up meta query vars
			 */
			if ( isset( $this->query_vars->meta_query ) ) {
				$media_post_query_args[ 'meta_query' ] = $this->query_vars->meta_query;
			}
			/**
			 * setting up taxonomy query vars
			 */
			if ( isset( $this->query_vars->tax_query ) ) {
				$media_post_query_args[ 'tax_query' ] = $this->query_vars->tax_query;
			}

			/**
			 * fetch relative data from WP_POST
			 */
			$media_post_query = new WP_Query( $media_post_query_args );

			/**
			 * Merge the data with media object of the album
			 */
			$media_post_data = $media_post_query->posts;

			foreach ( $media_post_data as $array_key => $post ) {
				$key = $this->get_media_by_media_id( $post->ID );

				$this->media[ $key ] = (object) (array_merge( (array) $this->media[ $key ], (array) $post ));

				$this->media[ $key ]->id = intval( $this->media[ $key ]->id );

				unset( $this->media[ $key ]->ID );
			}
		}
	}

	/**
	 * Checks at any point of time any media is left to be processed in the db pool
	 * @return boolean
	 */
	function have_media() {

		$total = $this->media_count;
		$curr = $this->current_media;
		$per_page = $this->action_query->per_page_media;
		$offset = ($this->action_query->page - 1) * $this->action_query->per_page_media;

		if ( $curr + 1 < $per_page && $total > $offset + $curr + 1 ) {
			return true;
		} elseif ( $curr + 1 == $per_page && $per_page > 0 ) {
			do_action_ref_array( 'rtmedia_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_media();
		}

		$this->in_the_media_loop = false;
		return false;
	}

	/**
	 * moves ahead in the loop of media within the album
	 * @global type $rtmedia_media
	 */
	function rtmedia() {
		global $rtmedia_media;
		$this->in_the_media_loop = true;

		if ( $this->current_media == -1 ) // loop has just started
			do_action_ref_array( 'rtmedia_loop_start', array( &$this ) );

		return $rtmedia_media = $this->next_media();
	}

	/**
	 * helper method for rt_album to move ahead in the db pool
	 * @return type
	 */
	function next_media() {
		$this->current_media ++;

		$this->rtmedia = $this->media[ $this->current_media ];
		return $this->rtmedia;
	}

	function permalink() {

		global $rtmedia_media;
		$parent_link = '';

		if ( function_exists( 'bp_core_get_user_domain' ) ) {
			$parent_link = bp_core_get_user_domain( $rtmedia_media->media_author );
		} else {
			$parent_link = get_author_posts_url( $rtmedia_media->media_author );
		}

		$link = trailingslashit( $parent_link . 'media/' . $rtmedia_media->id );

		return $link;
	}

	/**
	 * Rewinds the db pool of media album and resets it to begining
	 */
	function rewind_media() {
		$this->current_media = -1;
		if ( $this->action_query->per_page_media > 0 ) {
			$this->media = $this->media[ 0 ];
		}
	}

	/**
	 *
	 * @return type
	 */
	function &get_data() {

		$this->populate_data();

		return $this->media;
	}

}

?>
