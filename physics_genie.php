<?php

/*
Plugin Name: physics_genie
*/
function add_cors_http_header(){
  header("Access-Control-Allow-Origin: *");
}
add_action('init','add_cors_http_header');

$DEBUG = True; // Switches databases to staging ones, disable when pushing to productoin

class Physics_Genie {


  public function __construct() {

    // add_action('init', array($this, 'connect_another_db'));

    // Registers API routes
    add_action('rest_api_init', function(){
      // Registers the path /physics_genie/git-deploy-backend to update the plugin
      register_rest_route( 'physics_genie', '/git-deploy-backend', array(
        'methods'  => 'POST',
        'callback' => array($this, 'deploy_backend'),
      ));

      // Registers the path /physics_genie/git-deploy-frontend to update the webapp
      register_rest_route( 'physics_genie', '/git-deploy-frontend', array(
        'methods'  => 'POST',
        'callback' => array($this, 'deploy_frontend'),
      ));

      /* GET REQUESTS */
      //Get user metadata
      register_rest_route('physics_genie', 'user-metadata', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_user_metadata')
      ));

      //Get random next problem
      register_rest_route('physics_genie', 'problem', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_problem')
      ));

      //Get problem by problem id
      register_rest_route('physics_genie', 'problem/(?P<problem>\d+)', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_problem_by_id')
      ));

      //Get contributor problems from token
      register_rest_route('physics_genie', 'contributor-problems', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_contributor_problems')
      ));

      //Get submit data
      register_rest_route('physics_genie', 'submit-data', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_submit_data')
      ));

      //Get user info
      register_rest_route('physics_genie', 'user-info', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_user_info')
      ));

      //Get user stats
      register_rest_route('physics_genie', 'user-stats', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_user_stats')
      ));


      /* POST REQUESTS */
      //Register user
      register_rest_route('physics_genie', 'register', array(
        'methods' => 'POST',
        'callback' => array($this, 'register_user')
      ));

      //Password reset
      register_rest_route('physics_genie', 'password-reset', array(
        'methods' => 'POST',
        'callback' => array($this, 'password_reset')
      ));

      //Report problem error
      register_rest_route('physics_genie', 'report-problem-error', array(
        'methods' => 'POST',
        'callback' => array($this, 'report_problem_error')
      ));

      //Submit problem
      register_rest_route('physics_genie', 'submit-problem', array(
        'methods' => 'POST',
        'callback' => array($this, 'post_problem')
      ));

      //Add source
      register_rest_route('physics_genie', 'add-source', array(
        'methods' => 'POST',
        'callback' => array($this, 'post_source')
      ));

      //Add focus
      register_rest_route('physics_genie', 'add-focus', array(
        'methods' => 'POST',
        'callback' => array($this, 'post_focus')
      ));

      //Submit attempt
      register_rest_route('physics_genie', 'submit-attempt', array(
        'methods' => 'POST',
        'callback' => array($this, 'post_attempt')
      ));

      //Make external API call
      register_rest_route('physics_genie', 'external-request', array(
        'methods' => 'POST',
        'callback' => array($this, 'external_request')
      ));


      /* PUT REQUESTS */
      //Reset user
      register_rest_route('physics_genie', 'reset-user', array(
        'methods' => 'PUT',
        'callback' => array($this, 'reset_user')
      ));

      //Reset current problem
      register_rest_route('physics_genie', 'reset-curr-problem', array(
        'methods' => 'PUT',
        'callback' => array($this, 'reset_curr_problem')
      ));

      //Set user name
      register_rest_route('physics_genie', 'user-name', array(
        'methods' => 'PUT',
        'callback' => array($this, 'set_user_name')
      ));

      //Set user setup
      register_rest_route('physics_genie', 'user-setup', array(
        'methods' => 'PUT',
        'callback' => array($this, 'set_user_setup')
      ));

      //Edit problem
      register_rest_route('physics_genie', 'edit-problem', array(
        'methods' => 'PUT',
        'callback' => array($this, 'edit_problem')
      ));



    });
  }

  // public static function connect_another_db() {
  //  global $seconddb;
  //  $seconddb = new wpdb("physicsgenius", "Morin137!", "wordpress", "restored-instance-7-12-21.c4npn2kwj61c.us-west-1.rds.amazonaws.com");
  // }

  public static function getDB($db) {
    
  }


  public function deploy_backend() {
    require_once('deploy-backend.php');
  }

  public function deploy_frontend() {
    require_once('deploy-frontend.php');
  }

  // Method: POST, PUT, GET etc
  // Data: array("param" => "value") ==> index.php?param=value
  public function CallAPI($method, $url, $data = false)
  {
    $curl = curl_init();

    switch ($method)
    {
      case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);

        if ($data)
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        break;
      case "PUT":
        curl_setopt($curl, CURLOPT_PUT, 1);
        break;
      default:
        if ($data)
          $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);


    curl_close($curl);

    return $result;
  }


  // GET REQUESTS
  public function get_user_metadata() {
    $data = null;

    $data->contributor = ((current_user_can('administrator') || current_user_can('editor') || current_user_can('contributor')) ? true : false);

    return $data;
  }

  public function get_user_info() {
    global $wpdb;

    $data = null;

    $data->setup = $wpdb->get_results("SELECT curr_diff, curr_topics, curr_foci, calculus FROM pg_users WHERE user_id = ".get_current_user_id().";", OBJECT)[0];


    return $data;
  }

  public function get_user_stats($request_data) {
    global $wpdb;

    $stats = $wpdb->get_results(
      "SELECT
        topic,
        focus,
        num_presented,
        num_correct,
        avg_attempts,
        xp,
        streak,
        longest_winstreak,
        longest_losestreak
        FROM wordpress.pg_user_stats
        WHERE user_id = ".get_current_user_id()."
          AND ".(isset($request_data['topic']) ? 'topic = "'.$request_data['topic'].'"' : 'true')."
          AND ".(isset($request_data['focus']) ? 'focus = "'.$request_data['focus'].'"' : 'true')."
      ORDER BY topic, focus;", OBJECT);

    return $stats;
  }

  public function get_problem() {
    global $wpdb;

    if ($wpdb->get_results("SELECT curr_problem FROM pg_users WHERE user_id = ".get_current_user_id().";", OBJECT)[0]->curr_problem === null) {

      $problem = $wpdb->get_results(
        "SELECT *
        FROM wordpress.pg_problems
        WHERE
            (SELECT curr_topics
             FROM wordpress.pg_users
             WHERE user_id = 1) LIKE CONCAT('%', topic, '%')
          AND
            (SELECT curr_foci
             FROM wordpress.pg_users
             WHERE user_id = ".get_current_user_id().") LIKE CONCAT('%', main_focus, '%')
          AND difficulty >
            (SELECT curr_diff
             FROM wordpress.pg_users
             WHERE user_id = 1)
          AND difficulty <=
            (SELECT curr_diff
             FROM wordpress.pg_users
             WHERE user_id = ".get_current_user_id().") + IF(
                (SELECT curr_diff
                FROM wordpress.pg_users
                WHERE user_id = ".get_current_user_id().") = 2, 3, 2)
          AND IF(
                   (SELECT calculus
                    FROM wordpress.pg_users
                    WHERE user_id = ".get_current_user_id()."), TRUE, calculus != 'Required')
          AND problem_id NOT IN
            (SELECT problem_id
             FROM wordpress.pg_user_problems
             WHERE user_id = ".get_current_user_id().")
        ORDER BY RAND()
        LIMIT 1;", OBJECT);

      if (count($problem) == 0) {
        return null;
      } else {
        $problem = $problem[0];

        $wpdb->update(
          'pg_users',
          array(
            'curr_problem' => $problem->problem_id
          ),
          array(
            'user_id' => get_current_user_id()
          ),
          null,
          array('%d')
        );

        return $problem;
      }

    } else {
      return $wpdb->get_results("SELECT * FROM pg_problems WHERE problem_id = (SELECT curr_problem FROM pg_users WHERE user_id  = ".get_current_user_id().");", OBJECT)[0];
    }

  }

  public function get_problem_by_id( $data ) {
    global $wpdb;

    $problem = $wpdb->get_results("SELECT * FROM pg_problems WHERE pg_problems.problem_id = ".$data['problem'].";", OBJECT)[0];

    return $problem;

  }

  public function get_contributor_problems() {
    global $wpdb;

    $problem = $wpdb->get_results("SELECT * FROM pg_problems WHERE submitter = ".(get_current_user_id())." ORDER BY problem_id DESC;");

    if (get_current_user_id() === 1 || get_current_user_id() === 6 || get_current_user_id() === 16) {
      $problem = $wpdb->get_results("SELECT * FROM pg_problems ORDER BY problem_id DESC;");
    }

    return $problem;
  }

  public function get_submit_data() {
    global $wpdb;
    $data = null;
    $data->topics = $wpdb->get_results("SELECT topic, name FROM pg_topics WHERE focus = 'z';");
    $data->focuses = $wpdb->get_results("SELECT topic, focus, name FROM pg_topics WHERE topic = 0 AND focus != 'z';");
    $data->source_categories = $wpdb->get_results("SELECT DISTINCT category FROM pg_sources ORDER BY category;");
    $data->sources = $wpdb->get_results("SELECT * FROM pg_sources ORDER BY source;");
    return $data;
  }


  // POST REQUESTS
  public function register_user($request_data) {

    $user_data = array(
      'user_login'    => $request_data["username"],
      'user_email'    => $request_data["email"],
      'user_pass'     => $request_data["password"],
      'first_name'    => "",
      'last_name'     => "",
      'nickname'      => "",
    );

    $user_id = wp_insert_user( $user_data );

    if (is_wp_error($user_id)) {
      return $user_id->get_error_messages();
    }

    global $wpdb;
    $wpdb->insert(
      'pg_users',
      array(
        'user_id' => $user_id,
        'curr_diff' => 1,
        'curr_topics' => "0",
        'curr_foci' => "78",
        'calculus' => 1,
      )
    );

    $wpdb->insert(
      'pg_user_stats',
      array(
        'user_id' => $user_id,
        'topic' => 'z',
        'focus' => 'z',
        'num_presented' => 0,
        'num_saved' => 0,
        'num_correct' => 0,
        'avg_attempts' => 0,
        'xp' => 0,
        'streak' => 0,
        'longest_winstreak' => 0,
        'longest_losestreak' => 0
      )
    );

    foreach ($wpdb->get_results("SELECT topic, focus FROM pg_topics;", OBJECT) as $focus) {
      $wpdb->insert(
      'pg_user_stats',
        array(
          'user_id' => $user_id,
          'topic' => $focus->topic,
          'focus' => $focus->focus,
          'num_presented' => 0,
          'num_saved' => 0,
          'num_correct' => 0,
          'avg_attempts' => 0,
          'xp' => 0,
          'streak' => 0,
          'longest_winstreak' => 0,
          'longest_losestreak' => 0
        )
      );
    }

    return [];

  }

  public function password_reset($request_data) {

    $user_data = get_user_by('email', $request_data["email"]);

    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;
    $key = get_password_reset_key( $user_data );


    $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
    $message .= network_home_url( '/' ) . "\r\n\r\n";
    $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
    $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
    $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
    $message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    return wp_mail($user_email, "[Physics Genie] Password Reset", $message);
  }

  public function external_request($request_data) {
    return $this->CallAPI($request_data["method"], $request_data["url"], $request_data["data"]);
  }

  public function report_problem_error($request_data) {
    global $wpdb;
    $wpdb->insert(
      'pg_problem_errors',
      array(
        'user_id' => get_current_user_id(),
        'problem_id' => $request_data['problem_id'],
        'error_location' => ($request_data['error_location'] === "" ? null : $request_data['error_location']),
        'error_type' => ($request_data['error_type'] === "" ? null : $request_data['error_type']),
        'error_message' => ($request_data['error_message'] === "" ? null : $request_data['error_message']),
      )
    );

    return $wpdb->insert_id;
  }

  public function post_problem($request_data) {
    global $wpdb;
    $wpdb->insert(
      'pg_problems',
      array(
        'problem_text' => $request_data['problem_text'],
        'diagram' => ($request_data['diagram'] === "" ? null : $request_data['diagram']),
        'answer' => $request_data['answer'],
        'must_match' => ($request_data['must_match'] === 'true' ? 1 : 0),
        'error' => floatval($request_data['error']),
        'solution' => $request_data['solution'],
        'solution_diagram' => ($request_data['solution_diagram'] === "" ? null : $request_data['solution_diagram']),
        'hint_one' => $request_data['hint_one'],
        'hint_two' => ($request_data['hint_two'] === "" ? null : $request_data['hint_two']),
        'source' => intval($request_data['source']),
        'number_in_source' => $request_data['number_in_source'],
        'submitter' => get_current_user_id(),
        'difficulty' => intval($request_data['difficulty']),
        'calculus' => $request_data['calculus'],
        'topic' => $request_data['topic'],
        'main_focus' => $request_data['main_focus'],
        'other_foci' => ($request_data['other_foci'] === "" ? null: $request_data['other_foci']),
        'date_added' => date('Y-m-d')
      )
    );

    return $wpdb->insert_id;
  }

  public function post_source($request_data) {
    global $wpdb;

    $wpdb->insert('pg_sources',
      array(
        'category' => $request_data['category'],
        'author' => $request_data['author'],
        'source' => $request_data['source']
      )
    );

    return $wpdb->insert_id;
  }

  public function post_focus($request_data) {
    // IMPLEMENT THIS WHEN I NEED TO
  }

  public function post_attempt($request_data) {
    global $wpdb;


    $wpdb->insert(
      'pg_user_problems',
      array(
        'user_id' => get_current_user_id(),
        'problem_id' => intval($request_data['problem_id']),
        'num_attempts' => intval($request_data['num_attempts']),
        'correct' => ($request_data['correct'] === 'true' ? true : false),
        'saved' => false,
        'date_attempted' => date('Y-m-d H:i:s')
      )
    );

    $wpdb->update(
      'pg_users',
      array(
        'curr_problem' => null
      ),
      array(
        'user_id' => get_current_user_id()
      ),
      null,
      array('%d')
    );

    //Focus stats
    $curr_focus_stats = $wpdb->get_results("SELECT * FROM pg_user_stats WHERE user_id = ".get_current_user_id()." AND topic = '".$request_data['topic']."' AND focus = '".$request_data['focus']."';", OBJECT)[0];

    $focus_xp = $curr_focus_stats->xp;
    $focus_streak = $curr_focus_stats->streak;
    if ($request_data['correct'] === 'true' && $focus_streak > 0 && ($focus_streak + 1) % 5 === 0) {
      $focus_xp = intval(1.15*($curr_focus_stats->xp+intval($request_data['difficulty'])*(4-intval($request_data['num_attempts']))));
    } else if ($request_data['correct'] === 'true') {
      $focus_xp = $curr_focus_stats->xp+intval($request_data['difficulty'])*(4-intval($request_data['num_attempts']));
    } else if (!($request_data['correct'] === 'true') && $focus_streak < 0 && ($focus_streak - 1) % 3 === 0) {
      $focus_xp = intval(0.85*$curr_focus_stats->xp);
    }

    $wpdb->update(
      'pg_user_stats',
      array(
        'num_presented' => $curr_focus_stats->num_presented + 1,
        'num_correct' => $curr_focus_stats->num_correct + ($request_data['correct'] === 'true' ? 1 : 0),
        'avg_attempts' => ($curr_focus_stats->avg_attempts * $curr_focus_stats->num_presented + intval($request_data['num_attempts']))/($curr_focus_stats->num_presented + 1),
        'xp' => $focus_xp,
        'streak' => ($request_data['correct'] === 'true' ? ($focus_streak > 0 ? $focus_streak + 1 : 1) : ($focus_streak > 0 ? -1 : $focus_streak - 1)),
        'longest_winstreak' => (($request_data['correct'] === 'true' && $focus_streak >= $curr_focus_stats->longest_winstreak) ? ($focus_streak + 1) : ($request_data['correct'] === 'true' && $curr_focus_stats->longest_winstreak === 0 ? 1 : $curr_focus_stats->longest_winstreak)),
        'longest_losestreak' => ((!($request_data['correct'] === 'true') && -1*$focus_streak >= $curr_focus_stats->longest_losestreak) ? (1 - $focus_streak) : (!($request_data['correct'] === 'true') && $curr_focus_stats->longest_losestreak == 0 ? 1 : $curr_focus_stats->longest_losestreak))
      ),
      array(
        'user_id' => get_current_user_id(),
        'topic' => $request_data['topic'],
        'focus' => $request_data['focus']
      ),
      null,
      array('%d', '%s', '%s')
    );

    //Topic stats
    $curr_topic_stats = $wpdb->get_results("SELECT * FROM pg_user_stats WHERE user_id = ".get_current_user_id()." AND topic = '".$request_data['topic']."' AND focus = 'z';", OBJECT)[0];

    $wpdb->update(
      'pg_user_stats',
      array(
        'num_presented' => $curr_topic_stats->num_presented + 1,
        'num_correct' => $curr_topic_stats->num_correct + ($request_data['correct'] === 'true' ? 1 : 0),
        'avg_attempts' => ($curr_topic_stats->avg_attempts * $curr_topic_stats->num_presented + intval($request_data['num_attempts']))/($curr_topic_stats->num_presented + 1),
        'xp' => $curr_topic_stats->xp-$curr_focus_stats->xp+$focus_xp,
        'streak' => ($request_data['correct'] === 'true' ? ($curr_topic_stats->streak > 0 ? $curr_topic_stats->streak + 1 : 1) : ($curr_topic_stats->streak > 0 ? -1 : $curr_topic_stats->streak - 1)),
        'longest_winstreak' => (($request_data['correct'] === 'true' && $curr_topic_stats->streak >= $curr_topic_stats->longest_winstreak) ? ($curr_topic_stats->streak + 1) : ($request_data['correct'] === 'true' && $curr_topic_stats->longest_winstreak === 0 ? 1 : $curr_topic_stats->longest_winstreak)),
        'longest_losestreak' => ((!($request_data['correct'] === 'true') && -1*$curr_topic_stats->streak >= $curr_topic_stats->longest_losestreak) ? (1 - $curr_topic_stats->streak) : (!($request_data['correct'] === 'true') && $curr_topic_stats->longest_losestreak == 0 ? 1 : $curr_topic_stats->longest_losestreak))
      ),
      array(
        'user_id' => get_current_user_id(),
        'topic' => $request_data['topic'],
        'focus' => 'z'
      ),
      null,
      array('%d', '%s', '%s')
    );

    //User stats
    $curr_user_stats = $wpdb->get_results("SELECT * FROM pg_user_stats WHERE user_id = ".get_current_user_id()." AND topic = 'z' AND focus = 'z';", OBJECT)[0];

    $wpdb->update(
      'pg_user_stats',
      array(
        'num_presented' => $curr_user_stats->num_presented + 1,
        'num_correct' => $curr_user_stats->num_correct + ($request_data['correct'] === 'true' ? 1 : 0),
        'avg_attempts' => ($curr_user_stats->avg_attempts * $curr_user_stats->num_presented + intval($request_data['num_attempts']))/($curr_user_stats->num_presented + 1),
        'xp' => $curr_user_stats->xp-$curr_focus_stats->xp+$focus_xp,
        'streak' => ($request_data['correct'] === 'true' ? ($curr_user_stats->streak > 0 ? $curr_user_stats->streak + 1 : 1) : ($curr_user_stats->streak > 0 ? -1 : $curr_user_stats->streak - 1)),
        'longest_winstreak' => (($request_data['correct'] === 'true' && $curr_user_stats->streak >= $curr_user_stats->longest_winstreak) ? ($curr_user_stats->streak + 1) : ($request_data['correct'] === 'true' && $curr_user_stats->longest_winstreak === 0 ? 1 : $curr_user_stats->longest_winstreak)),
        'longest_losestreak' => ((!($request_data['correct'] === 'true') && -1*$curr_user_stats->streak >= $curr_user_stats->longest_losestreak) ? (1 - $curr_user_stats->streak) : (!($request_data['correct'] === 'true') && $curr_user_stats->longest_losestreak == 0 ? 1 : $curr_user_stats->longest_losestreak))
      ),
      array(
        'user_id' => get_current_user_id(),
        'topic' => 'z',
        'focus' => 'z'
      ),
      null,
      array('%d', '%s', '%s')
    );

    return 1;
  }


  // PUT REQUESTS
  public function reset_user($request_data) {

    global $wpdb;

    if (current_user_can('administrator')) {

      $wpdb->update(
        'pg_users',
        array(
          'curr_diff' => 0,
          'curr_topics' => '0',
          'curr_foci' => '78',
          'calculus' => true
        ),
        array(
          'user_id' => $request_data['user_id']
        ),
        null,
        array('%d')
      );

      $wpdb->update(
        'pg_user_stats',
        array(
          'num_presented' => 0,
          'num_saved' => 0,
          'num_correct' => 0,
          'avg_attempts' => 0,
          'xp' => 0,
          'streak' => 0,
          'longest_winstreak' => 0,
          'longest_losestreak' => 0
        ),
        array(
          'user_id' => $request_data['user_id'],
          'topic' => 'z',
          'focus' => 'z'
        ),
        null,
        array('%d', '%s', '%s')
      );

      foreach ($wpdb->get_results("SELECT topic, focus FROM pg_topics;", OBJECT) as $focus) {
        $wpdb->update(
          'pg_user_stats',
          array(
            'num_presented' => 0,
            'num_saved' => 0,
            'num_correct' => 0,
            'avg_attempts' => 0,
            'xp' => 0,
            'streak' => 0,
            'longest_winstreak' => 0,
            'longest_losestreak' => 0
          ),
          array(
            'user_id' => $request_data['user_id'],
            'topic' => $focus->topic,
            'focus' => $focus->focus,
          ),
          null,
          array('%d', '%s', '%s')
        );
      }

    }

    return 1;

  }

  public function reset_curr_problem() {
    global $wpdb;
    return $wpdb->update(
      'pg_users',
      array(
        'curr_problem' => null,
      ),
      array(
        'user_id' => get_current_user_id()
      ),
      null,
      array('%d')
    );
  }

  public function set_user_name($request_data) {
    global $wpdb;
    return $wpdb->update('wp_users', array(
      'user_login' => $request_data['name'],
      'user_nicename' => $request_data['name']
    ), array(
      'ID' => get_current_user_id()
    ), null, array('%d'));

  }

  public function set_user_setup($request_data) {
    global $wpdb;
    return $wpdb->update('pg_users', array(
      'curr_diff' => intval($request_data['curr_diff']),
      'curr_topics' => $request_data['curr_topics'],
      'curr_foci' => $request_data['curr_foci'],
      'calculus' => $request_data['calculus'] === 'true' ? 1 : 0
    ), array(
      'user_id' => get_current_user_id()
    ), array('%d', '%s', '%s', '%d'), array('%d'));
  }

  public function edit_problem($request_data) {
    global $wpdb;
    return $wpdb->update('pg_problems', array(
      'problem_text' => $request_data['problem_text'],
      'diagram' => ($request_data['diagram'] === "" ? null : $request_data['diagram']),
      'answer' => $request_data['answer'],
      'must_match' => ($request_data['must_match'] === 'true' ? 1 : 0),
      'error' => floatval($request_data['error']),
      'solution' => $request_data['solution'],
      'solution_diagram' => ($request_data['solution_diagram'] === "" ? null : $request_data['solution_diagram']),
      'hint_one' => $request_data['hint_one'],
      'hint_two' => ($request_data['hint_two'] === "" ? null : $request_data['hint_two']),
      'source' => intval($request_data['source']),
      'number_in_source' => $request_data['number_in_source'],
      'difficulty' => intval($request_data['difficulty']),
      'calculus' => $request_data['calculus'],
      'topic' => $request_data['topic'],
      'main_focus' => $request_data['main_focus'],
      'other_foci' => ($request_data['other_foci'] === "" ? null: $request_data['other_foci'])
    ), array(
      'problem_id' => $request_data['problem_id']
    ), null, array('%d'));
  }

}


// Initialize the plugin
$physics_genie = new Physics_Genie();


