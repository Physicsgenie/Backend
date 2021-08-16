<?php

/*
Plugin Name: physics_genie
*/
function add_cors_http_header(){
  header("Access-Control-Allow-Origin: *");
}

add_action('init','add_cors_http_header');

// Reads the debug config from config.php
require_once('config.php');

// Parses db names to add staging if debug is set
function getTable($tab) {
  if($GLOBALS['DEBUG']){
    global $PREFIX;
    return $PREFIX.$tab;
  }
  else
    return $tab;
}

class Physics_Genie {


  public function __construct() {

    // add_action('init', array($this, 'connect_another_db'));

    // Registers API routes
    add_action('rest_api_init', function(){
      // Registers the path /physics_genie/git-deploy-backend to update the plugin
      register_rest_route( 'physics_genie', '/git-deploy-backend', array(
        'methods'  => 'POST',
        'callback' => array($this, 'deploy_backend'),
        'permission_callback' => '__return_true'
      ));

      // Registers the path /physics_genie/git-deploy-frontend to update the webapp
      register_rest_route( 'physics_genie', '/git-deploy-frontend', array(
        'methods'  => 'POST',
        'callback' => array($this, 'deploy_frontend'),
        'permission_callback' => '__return_true'
      ));

      register_rest_route('physics_genie', 'user-metadata', array(
        'methods' => 'GET',
        'callback' => function() {
          $data = (object)[];

          $data->contributor = ((current_user_can('administrator') || current_user_can('editor') || current_user_can('contributor')) ? true : false);

          return $data;
        },
        'permission_callback' => '__return_true'
      ));

      // Change focus char to id
      function updateFocus($table, $newTable, $index, $column) {
        global $wpdb;
        $rows = $wpdb -> get_results(
          "SELECT ".$index.", ".$column."
          FROM wordpress.".getTable($table).";"
        );

        foreach ( $rows as $row ) {
          $char = $row -> $column;
          $focus_id = $wpdb -> get_results("
            SELECT topics_id
            FROM wordpress.".getTable('pg_topics_old')."
            WHERE focus = '".$char."'
          ;")[0] -> topics_id;
          $wpdb -> update(
            getTable($newTable),
            array(
              $column => $focus_id
            ),
            array(
              $index => $row -> $index
            )
          );
        }

      }

      // Replace foci string with serialized array
      function serializeColumn($table, $newTable, $index, $column) {
        global $wpdb;
        $rows = $wpdb -> get_results(
          "SELECT ".$index.", ".$column."
          FROM wordpress.".getTable($table).";"
        );

        foreach ( $rows as $row ) {
          $foci = [];
          $str = $row -> $column;
          $chars = str_split($str);
          foreach ( $chars as $char ) {
            $focus_id = $wpdb -> get_results("
              SELECT topics_id
              FROM wordpress.".getTable('pg_topics_old')."
              WHERE focus = '".$char."'
            ;")[0] -> topics_id;
            array_push($foci, $focus_id);
          }

          $wpdb -> update(
            getTable($newTable),
            array(
              $column => serialize($foci)
            ),
            array(
              $index => $row -> $index
            )
          );
        }
      }

      function convertAttempts($table, $newTable){
        global $wpdb;
        $problems = $wpdb -> get_results("
          SELECT *
          FROM wordpress.".getTable($table)."
        ;");

        foreach( $problems as $problem ){
          $attempts = $problem -> num_attempts;
          for( $i = 1; $i <= $attempts; $i++ ){
            if( $i == $attempts && $problem -> correct === "1" )
              $correct = True;
            else
              $correct = False;

            $wpdb -> insert(
              getTable($newTable),
              array(
                'user_id' => $problem -> user_id,
                'problem_id' => $problem -> problem_id,
                'student_answer' => '',
                'correct' => $correct,
                'give_up' => False,
                'date_attempted' => $problem -> date_attempted,
              )
            );
          }
          if( $attempts == 0 ){
            $wpdb -> insert(
              getTable($newTable),
              array(
                'user_id' => $problem -> user_id,
                'problem_id' => $problem -> problem_id,
                'student_answer' => null,
                'correct' => False,
                'give_up' => True,
                'date_attempted' => $problem -> date_attempted,
              )
            );
          }
        }
      }

      // Test functions endpoint
      if($GLOBALS['DEBUG']){
        register_rest_route('physics_genie', 'test', array(
          'methods' => 'GET',
          'callback' => function($request){
              // Update main_focus in pg_problems
              // updateFocus('pg_problems', 'pg_problems_new', 'problem_id', 'main_focus');
            convertAttempts('pg_user_problems', 'pg_user_attempts');
         },
          'permission_callback' => '__return_true'
        ));
      }


      /********* GET REQUESTS *********/

      /**
       * @api {get} /user-metadata Get User Metadata
       * @apiName GetMetadata
       * @apiGroup User
       * @apiDescription Retrieves contributor status of current user from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {Boolean} contributor Whether or not the User is a contributor.
       */
      register_rest_route('physics_genie', 'user-metadata', array(
        'methods' => 'GET',
        'callback' => function() {
          $data = (object)[];

          $data->contributor = ((current_user_can('administrator') || current_user_can('editor') || current_user_can('contributor')) ? true : false);

          return json_encode($data);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /problem Get Next Problem
       * @apiName GetProblem
       * @apiGroup Play
       * @apiDescription Retrieves user's current problem or gets random next problem
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {String} problem_id Id of problem.
       * @apiSuccess {String} problem_text Text of problem (LaTeX).
       * @apiSuccess {String} diagram Diagram of problem (svg). null if no diagram.
       * @apiSuccess {String} answer Correct answer of problem (in LaTeX form).
       * @apiSuccess {String} error Error margin of problem (must be "0" if algebraic answer).
       * @apiSuccess {String} must_match Whether or not the student's answer must match the correct answer exactly in form ("1" if it must match, "0" otherwise).
       * @apiSuccess {String} solution Solution of problem (LaTeX).
       * @apiSuccess {String} solution_diagram Solution diagram of problem (svg). null if no solution diagram.
       * @apiSuccess {String} hint_one First hint of problem (LaTeX).
       * @apiSuccess {String} hint_two Second hint of problem (LaTeX). null if no second hint.
       * @apiSuccess {String} source Id of the source of the problem.
       * @apiSuccess {String} number_in_source Number of problem within the source.
       * @apiSuccess {String} submitter User id of the person who submitted the problem.
       * @apiSuccess {String} difficulty Difficulty rating of problem (1-5).
       * @apiSuccess {String} calculus Whether problem requires calculus to solve ("None", "Required", or "Help").
       * @apiSuccess {String} topic Character id of topic of problem.
       * @apiSuccess {String} main_focus Character id of primary focus of problem.
       * @apiSuccess {String} other_foci Array of focus_ids corresponding to each focus.
       * @apiSuccess {String} date_added Date problem was submitted.
       */
      register_rest_route('physics_genie', 'problem', array(
        'methods' => 'GET',
        'callback' => function() {
          global $wpdb;

          if ($wpdb->get_results("SELECT curr_problem FROM ".getTable('pg_users')." WHERE user_id = ".get_current_user_id().";")[0]->curr_problem === null) {

            $foci = $wpdb -> get_results("
              SELECT curr_foci
              FROM wordpress.".getTable('pg_users')."
              WHERE user_id = ".get_current_user_id()."
            ;")[0] -> curr_foci;

            $fociCond = implode(', ', unserialize($foci));

            $difficulty = $wpdb -> get_results("
              SELECT curr_diff
              FROM wordpress.".getTable('pg_users')."
              WHERE user_id = ".get_current_user_id()."
            ;")[0] -> curr_diff;

            $problem = $wpdb->get_results("
              SELECT *
              FROM wordpress.".getTable('pg_problems')."
              WHERE
                main_focus IN (".$fociCond.")
                AND difficulty > ".$difficulty."
                AND difficulty <= ".($difficulty + 2)."
                AND IF(
                    (SELECT calculus
                    FROM wordpress.".getTable('pg_users')."
                    WHERE user_id = ".get_current_user_id()."), TRUE, calculus != 'Required')
                AND problem_id NOT IN
                  (SELECT problem_id
                   FROM wordpress.".getTable('pg_user_problems')."
                   WHERE user_id = ".get_current_user_id().")
              ORDER BY RAND()
              LIMIT 1
              ;");

            if (count($problem) == 0) {
              return null;
            } else {
              $problem = $problem[0];

              $wpdb->update(
                getTable('pg_users'),
                array(
                  'curr_problem' => $problem->problem_id
                ),
                array(
                  'user_id' => get_current_user_id()
                ),
                null,
                array('%d')
              );

              $problem -> other_foci = unserialize($problem -> other_foci);
              $problem -> topic = $wpdb -> get_results("
                SELECT topic
                FROM ".getTable("pg_foci")."
                WHERE focus_id = ".($problem -> main_focus)."
              ;")[0] -> topic;

            $foci = $wpdb -> get_results("
              SELECT curr_foci
              FROM wordpress.".getTable('pg_users')."
              WHERE user_id = ".get_current_user_id()."
            ;")[0] -> curr_foci;
              return new WP_REST_RESPONSE($problem);
            }

          } else {
            $problem = $wpdb->get_results("
              SELECT * FROM ".getTable('pg_problems')."
              WHERE problem_id = (SELECT curr_problem FROM ".getTable('pg_users')."
              WHERE user_id  = ".get_current_user_id().")
            ;")[0];
            $problem -> other_foci = unserialize($problem -> other_foci);
            $problem -> topic = $wpdb -> get_results("
              SELECT topic
              FROM ".getTable("pg_foci")."
              WHERE focus_id = ".($problem -> main_focus)."
            ;")[0] -> topic;
            return json_encode($problem);
          }
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /problem/:id Get Problem By Id
       * @apiName GetProblemById
       * @apiGroup Play
       * @apiDescription Retrieves problem by its id
       *
       * @apiSuccess {String} problem_id Id of problem.
       * @apiSuccess {String} problem_text Text of problem (LaTeX).
       * @apiSuccess {String} diagram Diagram of problem (svg). null if no diagram.
       * @apiSuccess {String} answer Correct answer of problem (in LaTeX form).
       * @apiSuccess {String} error Error margin of problem (must be "0" if algebraic answer).
       * @apiSuccess {String} must_match Whether or not the student's answer must match the correct answer exactly in form ("1" if it must match, "0" otherwise).
       * @apiSuccess {String} solution Solution of problem (LaTeX).
       * @apiSuccess {String} solution_diagram Solution diagram of problem (svg). null if no solution diagram.
       * @apiSuccess {String} hint_one First hint of problem (LaTeX).
       * @apiSuccess {String} hint_two Second hint of problem (LaTeX). null if no second hint.
       * @apiSuccess {String} source Id of the source of the problem.
       * @apiSuccess {String} number_in_source Number of problem within the source.
       * @apiSuccess {String} submitter User id of the person who submitted the problem.
       * @apiSuccess {String} difficulty Difficulty rating of problem (1-5).
       * @apiSuccess {String} calculus Whether problem requires calculus to solve ("None", "Required", or "Help").
       * @apiSuccess {String} topic Character id of topic of problem.
       * @apiSuccess {String} main_focus Character id of primary focus of problem.
       * @apiSuccess {String} other_foci Array of focus_ids corresponding to each focus.
       * @apiSuccess {String} date_added Date problem was submitted.
       */
      register_rest_route('physics_genie', 'problem/(?P<problem>\d+)', array(
        'methods' => 'GET',
        'callback' => function($data) {
          global $wpdb;

          $problem = $wpdb->get_results("SELECT * FROM ".getTable('pg_problems')." WHERE ".getTable('pg_problems').".problem_id = ".$data['problem'].";")[0];

          $problem -> topic = $wpdb -> get_results("
            SELECT topic
            FROM ".getTable("pg_foci")."
            WHERE focus_id = ".($problem -> main_focus)."
          ;")[0] -> topic;

          $problem -> other_foci = unserialize($problem -> other_foci);

          return json_encode($problem);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /contributor-problems Get Contributor Problems
       * @apiName GetContributorProblems
       * @apiGroup Submit
       * @apiDescription Retrieves past problems submitted by contributor from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {Array} data List of problems submitted by contributor. See "Get Next Problem" return fields for data form of each array entry.
       */
      register_rest_route('physics_genie', 'contributor-problems', array(
        'methods' => 'GET',
        'callback' => function() {
          global $wpdb;

          $problems = $wpdb->get_results("SELECT * FROM ".getTable('pg_problems')." WHERE submitter = ".(get_current_user_id())." ORDER BY problem_id DESC;");

          if (get_current_user_id() === 1 || get_current_user_id() === 6 || get_current_user_id() === 16) {
            $problems = $wpdb->get_results("SELECT * FROM ".getTable('pg_problems')." ORDER BY problem_id DESC;");
          }

          foreach( $problems as $problem )
            $problem -> other_foci = unserialize($problem -> other_foci);

          return json_encode($problems);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /submit-data Get Submit Data
       * @apiName GetSubmitData
       * @apiGroup Submit
       * @apiDescription Retrieves metadata of problems for submit page
       *
       * @apiSuccess {Array} topics List of possible topics. Each entry contains: "topic" field (string) containing character id of topic; "name" field (string) containing name of topic.
       * @apiSuccess {Array} focuses List of possible foci. Each entry contains: "topic" field (string) containing character id of the topic associated with focus; "focus" field (string) containing character id of focus; "name" field (string) containing name of focus.
       * @apiSuccess {Array} source_categories List of possible source categories. Each entry contains: "category" field (string) containing category.
       * @apiSuccess {Array} sources List of possible sources. Each entry contains: "source_id" field (string) containing id of source; "category" field (string) containing source category; "author" field (string) containing author of source; "source" field (string) name of source.
       */
      register_rest_route('physics_genie', 'submit-data', array(
        'methods' => 'GET',
        'callback' => function() {
          global $wpdb;
          $data = (object)[];

          $data -> topics = $wpdb -> get_results("
            SELECT * FROM ".getTable('pg_topics')." 
          ");

          $data -> focuses = $wpdb -> get_results("
            SELECT * FROM ".getTable('pg_foci')."
          ;");

          $data -> source_categories = $wpdb -> get_results("
            SELECT DISTINCT category FROM ".getTable('pg_sources')." 
            ORDER BY category
          ;");

          $data -> sources = $wpdb -> get_results("
            SELECT * FROM ".getTable('pg_sources')." 
            ORDER BY source
          ;");

          return json_encode($data);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /user-info Get User Info
       * @apiName GetUserInfo
       * @apiGroup User
       * @apiDescription Retrieves setup data of current user from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {Object} setup All return data (see other fields) is contained within this object.
       * @apiSuccess {String} curr_diff Current difficulty of user (0-2).
       * @apiSuccess {String} curr_topics String of concatenated character ids of current topics in user's settings.
       * @apiSuccess {String} curr_foci array of concatenated character ids of current foci in user's settings.
       * @apiSuccess {String} calculus Whether or not calculus problems are allowed ("1" if calculus is allowed, "0" otherwise).
       */
      register_rest_route('physics_genie', 'user-info', array(
        'methods' => 'GET',
        'callback' => function() {
          global $wpdb;

          $data = (object)[];

          $data -> setup = $wpdb -> get_results("
            SELECT curr_diff, curr_topics, curr_foci, calculus 
            FROM ".getTable('pg_users')." 
            WHERE user_id = ".get_current_user_id()."
          ;")[0];

          $data -> setup -> curr_topics = unserialize($data -> setup -> curr_topics);

          $data -> setup -> curr_foci = unserialize($data -> setup -> curr_foci);

          return json_encode($data);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {get} /user-stats Get User Stats
       * @apiName GetUserStats
       * @apiGroup User
       * @apiDescription Retrieves stats of current user from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {Array} data Return data is an array of objects containing topics+foci and their associated stats. Other fields describe the fields contained within each object of this array.
       * @apiSuccess {String} topic Character id of topic. "z" for entry containing overall user stats (summed across all topics).
       * @apiSuccess {String} focus Character id of focus. "z" for entry containing overall topic stats (or user stats if topic also equals "z") (summed across all foci).
       * @apiSuccess {String} num_presented Number of problems user has been presented with.
       * @apiSuccess {String} num_correct Number of problems user has gotten correct.
       * @apiSuccess {String} avg_attempts Average number of attempts of user.
       * @apiSuccess {String} xp Current XP of user. Corresponding level can be calculated with: Level = floor(sqrt(xp+9))-2.
       * @apiSuccess {String} streak Current streak of user (positive if winstreak, negative if losestreak).
       * @apiSuccess {String} longest_winstreak Longest winstreak of user (positive).
       * @apiSuccess {String} longest_losestreak Longest losestreak of user (positive).
       */
      register_rest_route('physics_genie', 'user-stats', array(
        'methods' => 'GET',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          global $wpdb;

          $topics = $wpdb -> get_results("
            SELECT topic_id, name 
            FROM ".getTable('pg_topics')."
          ;");

          // Create an object of topics and foci to calculate the stats
          $blank_stats = array (
            'num_presented' => 0,
            'num_completed' => 0,
            'num_correct' => 0,
            'num_incorrect' => 0,
            'num_attempts' => 0,
            'completed_attempts' => 0,
            'avg_attempts' => 0,
            'give_ups' => 0,
            'xp' => 0,
            'streak' => 0,
            'longest_winstreak' => 0,
            'longest_losestreak' => 0,
          );

          $all_stats = $blank_stats;

          foreach ( $topics as $topic ) {
            $foci = $wpdb -> get_results("
              SELECT focus_id, topic
              FROM ".getTable('pg_foci')."
              WHERE topic = ".$topic -> topic_id."
            ;");


            $all_stats['topic_stats'][$topic -> topic_id] = $blank_stats;
            foreach ( $foci as $focus ) {
              $all_stats['topic_stats'][$topic -> topic_id]['focus_stats'][$focus -> focus_id] = $blank_stats;
            }
          }

          // Loop through and calculate stats

          $attempts = $wpdb -> get_results("
            SELECT *
            FROM ".getTable('pg_user_attempts')."
            WHERE user_id = ".get_current_user_id()."
            ORDER BY date_attempted ASC, problem_id
          ;");

          // Group the attempts into arrays of problems
          $problems = [];
          foreach ( $attempts as $attempt ) {
            $problems[$attempt -> problem_id][] = $attempt;
          }

          // Sort the problems by the date of the last attempt (ensures completed problems are always in order)
          uasort($problems, function ($a, $b) {
            return end($a) -> user_attempt_id <=> end($b) -> user_attempt_id;
          });

          foreach ( $problems as $problem_id => $problem  ) {
            $problem_info = $wpdb -> get_results("
              SELECT main_focus, difficulty
              FROM ".getTable('pg_problems')."
              WHERE problem_id = ".$problem_id."
            ;")[0];

            $focus = $problem_info -> main_focus;

            $topic = $wpdb -> get_results("
              SELECT topic
              FROM ".getTable('pg_foci')."
              WHERE focus_id = ".$focus."
            ;")[0] -> topic;

            // Whether or not the problem is correct
            $correct = False;
            // A problem is not completed if the user still has attempts remaining
            $completed = False;
            // Only counts attempts for completed problems
            $completed_attempts = 0;

            // Loop through the problem attempts until a correct answer or a give up
            $break = False;
            for( $i = 0; $i < count($problem); $i++ ) {
              if($problem[$i] -> give_up === "1"){
                $completed = True;
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['give_ups'] ++;
                $all_stats['topic_stats'][$topic]['give_ups'] ++;
                $all_stats['give_ups'] ++;
                $break = True;
              }
              if($problem[$i] -> correct === "1"){
                $correct = True;
                $completed = True;
                $break = True;
              }
              // Increase attempts if not a give up
              if( $problem[$i] -> give_up === "0" ){
                $completed_attempts ++;
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_attempts'] ++;
                $all_stats['topic_stats'][$topic]['num_attempts'] ++;
                $all_stats['num_attempts'] ++;
              }

              if( $break )
                break;
            }

            // Set the problem as completed if all attempts have been used
            if( $completed === False && count($problem) >= 3 )
              $completed = True;

            // Increase presented problems
            $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_presented'] ++;
            $all_stats['topic_stats'][$topic]['num_presented'] ++;
            $all_stats['num_presented'] ++;

            if( $completed ) {
              // Increment completed attempts
              $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['completed_attempts'] += $completed_attempts;
              $all_stats['topic_stats'][$topic]['completed_attempts'] +- $completed_attempts;
              $all_stats['completed_attempts'] += $completed_attempts;

              // Increment completed problems
              $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_completed'] ++;
              $all_stats['topic_stats'][$topic]['num_completed'] ++;
              $all_stats['num_completed'] ++;

              if( $correct ){
                // Increment correct problems
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_correct'] ++;
                $all_stats['topic_stats'][$topic]['num_correct'] ++;
                $all_stats['num_correct'] ++;

                // Increment xp based on difficulty
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['xp'] += $problem_info -> difficulty * 2;
                $all_stats['topic_stats'][$topic]['xp'] += $problem_info -> difficulty * 2;
                $all_stats['xp'] += $problem_info -> difficulty * 2;

                // Set the focus streak stats
                if( $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] >= 0 )
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] ++;
                elseif( $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] < 0 )
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] = 1;

                // Set the topic streak stats
                if( $all_stats['topic_stats'][$topic]['streak'] >= 0 )
                  $all_stats['topic_stats'][$topic]['streak'] ++;
                elseif( $all_stats['topic_stats'][$topic]['streak'] < 0 )
                  $all_stats['topic_stats'][$topic]['streak'] = 1;

                // Set the overall streak stats
                if( $all_stats['streak'] >= 0 )
                  $all_stats['streak'] ++;
                elseif( $all_stats['streak'] < 0 )
                  $all_stats['streak'] = 1;

              } else {
                // Increment incorrect problems
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_incorrect'] ++;
                $all_stats['topic_stats'][$topic]['num_incorrect'] ++;
                $all_stats['num_incorrect'] ++;
 
                // Set the focus streak stats
                if( $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] <= 0 )
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] --;
                elseif( $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] > 0 )
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'] = -1;

                // Set the topic streak stats
                if( $all_stats['topic_stats'][$topic]['streak'] <= 0 )
                  $all_stats['topic_stats'][$topic]['streak'] --;
                elseif( $all_stats['topic_stats'][$topic]['streak'] > 0 )
                  $all_stats['topic_stats'][$topic]['streak'] = -1;

                // Set the overall streak stats
                if( $all_stats['streak'] <= 0 )
                  $all_stats['streak'] --;
                elseif( $all_stats['streak'] > 0 )
                  $all_stats['streak'] = -1;

             }

              // Set the focus longest winstreak
              $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['longest_winstreak'] = 
                max(
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['longest_winstreak'],
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'],
                );

              // Set the focus longest losestreak
              $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['longest_losestreak'] = 
                min(
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['longest_losestreak'],
                  $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak'],
                );

              // Set the topic longest winstreak
              $all_stats['topic_stats'][$topic]['longest_winstreak'] =
                max(
                  $all_stats['topic_stats'][$topic]['longest_winstreak'],
                  $all_stats['topic_stats'][$topic]['streak'],
                );

              // Set the topic longest losestreak
              $all_stats['topic_stats'][$topic]['longest_losestreak'] =
                min(
                  $all_stats['topic_stats'][$topic]['longest_losestreak'],
                  $all_stats['topic_stats'][$topic]['streak'],
                );

              // Set the overall longest winstreak
              $all_stats['longest_winstreak'] = 
                max(
                  $all_stats['longest_winstreak'],
                  $all_stats['streak'],
                );

              // Set the overall longest losestreak
              $all_stats['longest_losestreak'] = 
                min(
                  $all_stats['longest_losestreak'],
                  $all_stats['streak'],
                );

              // Set the average attempts
              $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['avg_attempts'] = 
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['completed_attempts']/
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['num_completed'];

              $all_stats['topic_stats'][$topic]['avg_attempts'] = 
                $all_stats['topic_stats'][$topic]['completed_attempts']/
                $all_stats['topic_stats'][$topic]['num_completed'];

              $all_stats['avg_attempts'] = 
                $all_stats['completed_attempts']/
                $all_stats['num_completed'];
            }

          }

          // Convert topic and focus ids to names
          $topic_stats = [];
          foreach ( $all_stats['topic_stats'] as $topic_id => $topic_stat ){
            $topic_name = $wpdb -> get_results("
              SELECT name
              FROM ".getTable('pg_topics')."
              WHERE topic_id = ".$topic_id."
              ;")[0] -> name;

            $focus_stats = [];
            foreach ( $topic_stat['focus_stats'] as $focus_id => $focus_stat ){
              $focus_name = $wpdb -> get_results("
                SELECT name
                FROM ".getTable('pg_foci')."
                WHERE focus_id = ".$focus_id."
                ;")[0] -> name;

              $focus_stat['longest_losestreak'] = - $focus_stat['longest_losestreak'];

              $focus_stats[$focus_name] = $focus_stat;
            }

            $topic_stat['focus_stats'] = $focus_stats;
            $topic_stat['longest_losestreak'] = - $topic_stat['longest_losestreak'];
            $topic_stats[$topic_name] = $topic_stat;

          }

          $all_stats['topic_stats'] = $topic_stats;
          $all_stats['longest_losestreak'] = - $all_stats['longest_losestreak'];


          return json_encode($all_stats);
        },
        'permission_callback' => '__return_true'
      ));



      /********* POST REQUESTS *********/

      /**
       * @api {post} /register Register
       * @apiName Register
       * @apiGroup User
       * @apiDescription Registers user or returns wordpress error(s) if unsuccessful.
       *
       * @apiParam {String} email Email of user to be registered
       * @apiparam {String} username Username of user to be registered
       * @apiParam {String} password Password of user to be registered
       *
       * @apiSuccess {Array} data Array of wordpress errors (empty array if successful registration).
       */
      register_rest_route('physics_genie', 'register', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          $user_data = array(
            'user_login'    => $json["username"],
            'user_email'    => $json["email"],
            'user_pass'     => $json["password"],
            'first_name'    => "",
            'last_name'     => "",
            'nickname'      => "",
          );

          $user_id = wp_insert_user( $user_data );

          if (is_wp_error($user_id)) {
            return json_encode($user_id->get_error_messages());
          }

          global $wpdb;
          $wpdb->insert(
            getTable('pg_users'),
            array(
              'user_id' => $user_id,
              'curr_diff' => 1,
              'curr_topics' => "0",
              'curr_foci' => serialize(
                array(
                  9,
                  10,
                )
              ),
              'calculus' => 1,
            )
          );

          foreach ($wpdb->get_results("SELECT topic, focus FROM ".getTable('pg_topics').";") as $focus) {
            $wpdb->insert(
              getTable('pg_user_stats'),
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

          return json_encode([]);
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {post} /password-reset Password Reset
       * @apiName PasswordReset
       * @apiGroup User
       * @apiDescription Sends reset password email to specified address
       *
       * @apiParam {String} email Email address to which to send the reset password message and link.
       *
       * @apiSuccess {Boolean} data Indicates if password reset email was successfully sent. Returns error page if unsuccessful.
       */
      register_rest_route('physics_genie', 'password-reset', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          $user_data = get_user_by('email', $json["email"]);

          $user_login = $user_data->user_login;
          $user_email = $user_data->user_email;
          $key = get_password_reset_key( $user_data );


          $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
          $message .= network_home_url( '/' ) . "\r\n\r\n";
          $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
          $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
          $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
          $message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

          return json_encode(wp_mail($user_email, "[Physics Genie] Password Reset", $message));

        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {post} /report-problem-error Report Problem Error
       * @apiName ReportProblemError
       * @apiGroup Play
       * @apiDescription Records problem error in database
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiParam {Number} problem_id Id of problem being reported.
       * @apiParam {String} error_location Location of error. Possible values: "Problem Repeat", "Metadata", "Problem Text", "Answer", "Solution", and "Other".
       * @apiParam {String} error_type Type of error. Possible values depend on error location.
       * @apiParam {String} error_message Description of error.
       *
       * @apiSuccess {Number} data Id of successful report in problem error database.
       */
      register_rest_route('physics_genie', 'report-problem-error', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data -> get_body());
          global $wpdb;
          $wpdb->insert(
            getTable('pg_problem_errors'),
            array(
              'user_id' => get_current_user_id(),
              'problem_id' => $json -> problem_id,
              'error_location' => ($json -> error_location === "" ? null : $json -> error_location),
              'error_type' => ($json -> error_type === "" ? null : $json -> error_type),
              'error_message' => ($json -> error_message === "" ? null : $json -> error_message),
            )
          );

          return $wpdb->insert_id;
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {post} /submit-problem Submit Problem
       * @apiName SubmitProblem
       * @apiGroup Submit
       * @apiDescription Submits problem to database
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiParam {String} problem_text Text of problem (LaTeX).
       * @apiParam {String} diagram Diagram of problem (svg). Empty string if no diagram.
       * @apiParam {String} answer Correct answer of problem (in LaTeX form).
       * @apiParam {String} error Error margin (percent) of problem (must be "0" if algebraic answer).
       * @apiParam {String} must_match Whether or not the student's answer must match the correct answer exactly in form ("true" if it must match, "false" otherwise).
       * @apiParam {String} solution Solution of problem (LaTeX).
       * @apiParam {String} solution_diagram Solution diagram of problem (svg). Empty string if no solution diagram.
       * @apiParam {String} hint_one First hint of problem (LaTeX).
       * @apiParam {String} hint_two Second hint of problem (LaTeX). Empty string if no second hint.
       * @apiParam {String} source Id of the source of the problem.
       * @apiParam {String} number_in_source Number of problem within the source.
       * @apiParam {String} difficulty Difficulty rating of problem (1-5).
       * @apiParam {String} calculus Whether problem requires calculus to solve ("None", "Required", or "Help").
       * @apiParam {String} topic Character id of topic of problem.
       * @apiParam {String} main_focus Character id of primary focus of problem.
       * @apiParam {String} other_foci Array of focus_ids. Empty string if no other foci.
       *
       * @apiSuccess {Number} data Id of new problem in problem database on success.
       */
      register_rest_route('physics_genie', 'submit-problem', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);

          global $wpdb;
          $wpdb->insert(
            getTable('pg_problems'),
            array(
              'problem_text' => $json['problem_text'],
              'diagram' => ($json['diagram'] === "" ? null : $json['diagram']),
              'answer' => $json['answer'],
              'must_match' => ($json['must_match'] === 'true' ? 1 : 0),
              'error' => floatval($json['error']),
              'solution' => $json['solution'],
              'solution_diagram' => ($json['solution_diagram'] === "" ? null : $json['solution_diagram']),
              'hint_one' => $json['hint_one'],
              'hint_two' => ($json['hint_two'] === "" ? null : $json['hint_two']),
              'source' => intval($json['source']),
              'number_in_source' => $json['number_in_source'],
              'submitter' => get_current_user_id(),
              'difficulty' => intval($json['difficulty']),
              'calculus' => $json['calculus'],
              'topic' => $json['topic'],
              'main_focus' => $json['main_focus'],
              'other_foci' => ($json['other_foci'] === "" ? null: serialize($json['other_foci'])),
              'date_added' => date('Y-m-d')
            )
          );

          return $wpdb->insert_id;
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {post} /add-source Add Source
       * @apiName AddSource
       * @apiGroup Submit
       * @apiDescription Adds new source to list of possible sources
       *
       * @apiParam {String} category Category of new source.
       * @apiParam {String} author Author new source.
       * @apiParam {String} source Name of new source.
       *
       * @apiSuccess {Number} data Id of source in source database on success.
       */
      register_rest_route('physics_genie', 'add-source', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);

          global $wpdb;

          $wpdb->insert(getTable('pg_sources'),
            array(
              'category' => $json['category'],
              'author' => $json['author'],
              'source' => $json['source']
            )
          );

          return $wpdb->insert_id;
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {post} /submit-attempt Submit Attempt
       * @apiName SubmitAttempt
       * @apiGroup Play
       * @apiDescription Submits a user's complete attempt of a problem (with result)
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiParam {String} problem_id Id of attempted problem.
       * @apiParam {String} num_attempts Number of attempts on problem.
       * @apiParam {String} correct Whether or not the problem was correct ("true" if correct, "false" otherwise).
       * @apiParam {String} difficulty Difficulty of attempted problem (1-5).
       *
       * @apiSuccess {Number} data Returns 1 on success.
       */
      register_rest_route('physics_genie', 'submit-attempt', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          global $wpdb;


          $wpdb->insert(
            getTable('pg_user_problems'),
            array(
              'user_id' => get_current_user_id(),
              'problem_id' => intval($json['problem_id']),
              'num_attempts' => intval($json['num_attempts']),
              'correct' => ($json['correct'] === 'true' ? true : false),
              'saved' => false,
              'date_attempted' => date('Y-m-d H:i:s')
            )
          );

          $wpdb->update(
            getTable('pg_users'),
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
          $curr_focus_stats = $wpdb->get_results("SELECT * FROM ".getTable('pg_user_stats')." WHERE user_id = ".get_current_user_id()." AND topic = '".$json['topic']."' AND focus = '".$json['focus']."';")[0];

          $focus_xp = $curr_focus_stats->xp;
          $focus_streak = $curr_focus_stats->streak;
          if ($json['correct'] === 'true' && $focus_streak > 0 && ($focus_streak + 1) % 5 === 0) {
            $focus_xp = intval(1.15*($curr_focus_stats->xp+intval($json['difficulty'])*(4-intval($json['num_attempts']))));
          } else if ($json['correct'] === 'true') {
            $focus_xp = $curr_focus_stats->xp+intval($json['difficulty'])*(4-intval($json['num_attempts']));
          } else if (!($json['correct'] === 'true') && $focus_streak < 0 && ($focus_streak - 1) % 3 === 0) {
            $focus_xp = intval(0.85*$curr_focus_stats->xp);
          }

          $wpdb->update(
            getTable('pg_user_stats'),
            array(
              'num_presented' => $curr_focus_stats->num_presented + 1,
              'num_correct' => $curr_focus_stats->num_correct + ($json['correct'] === 'true' ? 1 : 0),
              'avg_attempts' => ($curr_focus_stats->avg_attempts * $curr_focus_stats->num_presented + intval($json['num_attempts']))/($curr_focus_stats->num_presented + 1),
              'xp' => $focus_xp,
              'streak' => ($json['correct'] === 'true' ? ($focus_streak > 0 ? $focus_streak + 1 : 1) : ($focus_streak > 0 ? -1 : $focus_streak - 1)),
              'longest_winstreak' => (($json['correct'] === 'true' && $focus_streak >= $curr_focus_stats->longest_winstreak) ? ($focus_streak + 1) : ($json['correct'] === 'true' && $curr_focus_stats->longest_winstreak === 0 ? 1 : $curr_focus_stats->longest_winstreak)),
              'longest_losestreak' => ((!($json['correct'] === 'true') && -1*$focus_streak >= $curr_focus_stats->longest_losestreak) ? (1 - $focus_streak) : (!($json['correct'] === 'true') && $curr_focus_stats->longest_losestreak == 0 ? 1 : $curr_focus_stats->longest_losestreak))
            ),
            array(
              'user_id' => get_current_user_id(),
              'topic' => $json['topic'],
              'focus' => $json['focus']
            ),
            null,
            array('%d', '%s', '%s')
          );

          //Topic stats
          $curr_topic_stats = $wpdb->get_results("SELECT * FROM ".getTable('pg_user_stats')." WHERE user_id = ".get_current_user_id()." AND topic = '".$json['topic']."' AND focus = 'z';")[0];

          $wpdb->update(
            getTable('pg_user_stats'),
            array(
              'num_presented' => $curr_topic_stats->num_presented + 1,
              'num_correct' => $curr_topic_stats->num_correct + ($json['correct'] === 'true' ? 1 : 0),
              'avg_attempts' => ($curr_topic_stats->avg_attempts * $curr_topic_stats->num_presented + intval($json['num_attempts']))/($curr_topic_stats->num_presented + 1),
              'xp' => $curr_topic_stats->xp-$curr_focus_stats->xp+$focus_xp,
              'streak' => ($json['correct'] === 'true' ? ($curr_topic_stats->streak > 0 ? $curr_topic_stats->streak + 1 : 1) : ($curr_topic_stats->streak > 0 ? -1 : $curr_topic_stats->streak - 1)),
              'longest_winstreak' => (($json['correct'] === 'true' && $curr_topic_stats->streak >= $curr_topic_stats->longest_winstreak) ? ($curr_topic_stats->streak + 1) : ($json['correct'] === 'true' && $curr_topic_stats->longest_winstreak === 0 ? 1 : $curr_topic_stats->longest_winstreak)),
              'longest_losestreak' => ((!($json['correct'] === 'true') && -1*$curr_topic_stats->streak >= $curr_topic_stats->longest_losestreak) ? (1 - $curr_topic_stats->streak) : (!($json['correct'] === 'true') && $curr_topic_stats->longest_losestreak == 0 ? 1 : $curr_topic_stats->longest_losestreak))
            ),
            array(
              'user_id' => get_current_user_id(),
              'topic' => $json['topic'],
              'focus' => 'z'
            ),
            null,
            array('%d', '%s', '%s')
          );

          //User stats
          $curr_user_stats = $wpdb->get_results("SELECT * FROM ".getTable('pg_user_stats')." WHERE user_id = ".get_current_user_id()." AND topic = 'z' AND focus = 'z';")[0];

          $wpdb->update(
            getTable('pg_user_stats'),
            array(
              'num_presented' => $curr_user_stats->num_presented + 1,
              'num_correct' => $curr_user_stats->num_correct + ($json['correct'] === 'true' ? 1 : 0),
              'avg_attempts' => ($curr_user_stats->avg_attempts * $curr_user_stats->num_presented + intval($json['num_attempts']))/($curr_user_stats->num_presented + 1),
              'xp' => $curr_user_stats->xp-$curr_focus_stats->xp+$focus_xp,
              'streak' => ($json['correct'] === 'true' ? ($curr_user_stats->streak > 0 ? $curr_user_stats->streak + 1 : 1) : ($curr_user_stats->streak > 0 ? -1 : $curr_user_stats->streak - 1)),
              'longest_winstreak' => (($json['correct'] === 'true' && $curr_user_stats->streak >= $curr_user_stats->longest_winstreak) ? ($curr_user_stats->streak + 1) : ($json['correct'] === 'true' && $curr_user_stats->longest_winstreak === 0 ? 1 : $curr_user_stats->longest_winstreak)),
              'longest_losestreak' => ((!($json['correct'] === 'true') && -1*$curr_user_stats->streak >= $curr_user_stats->longest_losestreak) ? (1 - $curr_user_stats->streak) : (!($json['correct'] === 'true') && $curr_user_stats->longest_losestreak == 0 ? 1 : $curr_user_stats->longest_losestreak))
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
        },
        'permission_callback' => '__return_true'

      ));


      /**
       * @api {post} /external-request External Request
       * @apiName SubmitAttempt
       * @apiGroup Misc
       * @apiDescription Makes a request to an external API
       *
       * @apiParam {String} method Method of external request ("GET", "POST", "PUT", etc.).
       * @apiParam {String} url URL of external request.
       *
       * @apiSuccess {Object} data Data received from external request.
       */
      register_rest_route('physics_genie', 'external-request', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          return $this->CallAPI($json["method"], $json["url"], $json["data"]);
        },
        'permission_callback' => '__return_true'

      ));



      /********* PUT REQUESTS *********/

      /**
       * @api {put} /reset-curr-problem Reset Current Problem
       * @apiName ResetCurrProblem
       * @apiGroup Play
       * @apiDescription Deletes current problem of user from Web Token (equivalent to "Skip" functionality)
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiSuccess {Number} data Returns 1 on success.
       */
      register_rest_route('physics_genie', 'reset-curr-problem', array(
        'methods' => 'PUT',
        'callback' => function() {
          global $wpdb;
          return $wpdb->update(
            getTable('pg_users'),
            array(
              'curr_problem' => null,
            ),
            array(
              'user_id' => get_current_user_id()
            ),
            null,
            array('%d')
          );
        },
        'permission_callback' => '__return_true'

      ));


      /**
       * @api {put} /user-name Set User Name
       * @apiName SetUserName
       * @apiGroup User
       * @apiDescription Sets user name of current user from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiParam {String} name New user name of user.
       *
       * @apiSuccess {Number} data Returns 1 on success.
       */
      register_rest_route('physics_genie', 'user-name', array(
        'methods' => 'PUT',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          global $wpdb;
          return $wpdb->update('wp_users', array(
            'user_login' => $json['name'],
            'user_nicename' => $json['name']
          ), array(
            'ID' => get_current_user_id()
          ), null, array('%d'));
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {put} /user-setup Set User Setup
       * @apiName SetUserSetup
       * @apiGroup User
       * @apiDescription Sets user setup of current user from Web Token
       *
       * @apiHeader {String} Authorization "Bearer " + user's current JSON Web Token (JWT).
       *
       * @apiParam {String} curr_diff New general difficulty rating of problems presented to user (0: 1-2 star problems; 1: 2-3 star problems; 2: 3-5 star problems).
       * @apiParam {String} curr_topics String of concatenated character ids of new list of topics of the problems presented to user.
       * @apiParam {String} curr_foci String of concatenated character ids of new list of foci of the problems presented to user.
       * @apiParam {String} calculus Whether or not calculus problems are allowed ("true" if calculus is allowed, "false" otherwise).
       *
       * @apiSuccess {Number} data Returns 1 on success.
       */
      register_rest_route('physics_genie', 'user-setup', array(
        'methods' => 'PUT',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          global $wpdb;
          return $wpdb->update(getTable('pg_users'), array(
            'curr_diff' => intval($json['curr_diff']),
            'curr_topics' => $json['curr_topics'],
            'curr_foci' => $json['curr_foci'],
            'calculus' => $json['calculus'] === 'true' ? 1 : 0
          ), array(
            'user_id' => get_current_user_id()
          ), array('%d', '%s', '%s', '%d'), array('%d'));
        },
        'permission_callback' => '__return_true'
      ));


      /**
       * @api {put} /edit-problem Edit Problem
       * @apiName EditProblem
       * @apiGroup Submit
       * @apiDescription Edits problem previously submitted to database
       *
       * @apiParam {String} problem_id Id of problem to be edited.
       * @apiParam {String} problem_text Text of problem (LaTeX).
       * @apiParam {String} diagram Diagram of problem (svg). Empty string if no diagram.
       * @apiParam {String} answer Correct answer of problem (in LaTeX form).
       * @apiParam {String} error Error margin (percent) of problem (must be "0" if algebraic answer).
       * @apiParam {String} must_match Whether or not the student's answer must match the correct answer exactly in form ("true" if it must match, "false" otherwise).
       * @apiParam {String} solution Solution of problem (LaTeX).
       * @apiParam {String} solution_diagram Solution diagram of problem (svg). Empty string if no solution diagram.
       * @apiParam {String} hint_one First hint of problem (LaTeX).
       * @apiParam {String} hint_two Second hint of problem (LaTeX). Empty string if no second hint.
       * @apiParam {String} source Id of the source of the problem.
       * @apiParam {String} number_in_source Number of problem within the source.
       * @apiParam {String} difficulty Difficulty rating of problem (1-5).
       * @apiParam {String} calculus Whether problem requires calculus to solve ("None", "Required", or "Help").
       * @apiParam {String} topic Character id of topic of problem.
       * @apiParam {String} main_focus Character id of primary focus of problem.
       * @apiParam {String} other_foci Array of focus_ids. Empty string if no other foci.
       *
       * @apiSuccess {Number} data Returns 1 on success.
       */
      register_rest_route('physics_genie', 'edit-problem', array(
        'methods' => 'PUT',
        'callback' => function($request_data) {
          $json = json_decode($request_data);
          global $wpdb;
          return $wpdb->update(getTable('pg_problems'), array(
            'problem_text' => $json['problem_text'],
            'diagram' => ($json['diagram'] === "" ? null : $json['diagram']),
            'answer' => $json['answer'],
            'must_match' => ($json['must_match'] === 'true' ? 1 : 0),
            'error' => floatval($json['error']),
            'solution' => $json['solution'],
            'solution_diagram' => ($json['solution_diagram'] === "" ? null : $json['solution_diagram']),
            'hint_one' => $json['hint_one'],
            'hint_two' => ($json['hint_two'] === "" ? null : $json['hint_two']),
            'source' => intval($json['source']),
            'number_in_source' => $json['number_in_source'],
            'difficulty' => intval($json['difficulty']),
            'calculus' => $json['calculus'],
            'topic' => $json['topic'],
            'main_focus' => $json['main_focus'],
            'other_foci' => ($json['other_foci'] === "" ? null: serialize($json['other_foci']))
          ), array(
            'problem_id' => $json['problem_id']
          ), null, array('%d'));
        },
        'permission_callback' => '__return_true'
      ));

    });
  }

  // public static function connect_another_db() {
  //  global $seconddb;
  //  $seconddb = new wpdb("physicsgenius", "Morin137!", "wordpress", "restored-instance-7-12-21.c4npn2kwj61c.us-west-1.rds.amazonaws.com");
  // }

  // Call backend deploy script
  public function deploy_backend() {
    require_once('deploy-backend.php');
  }

  // Call frontend deploy script
  public function deploy_frontend() { 
    require_once('deploy-frontend.php');
  }

  // Method: POST, PUT, GET etc
  // Data: array("param" => "value") ==> index.php?param=value
  public function CallAPI($method, $url, $data)
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
        if (isset($data))
          $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);


    curl_close($curl);

    return $result;
  }

}


// Initialize the plugin
$physics_genie = new Physics_Genie();


