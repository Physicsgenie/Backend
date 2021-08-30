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

          $data -> contributor =
            current_user_can('administrator') ||
            current_user_can('editor') || 
            current_user_can('contributor');

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
            ),
            null,
            array('%d')
          );
        }
      }

      // Replace foci string with serialized array
      function serializeFoci($table, $newTable, $index, $column) {
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
              SELECT focus
              FROM wordpress.".getTable('pg_topics_old')."
              WHERE focus = '".$char."'
            ;")[0] -> focus;
            array_push($foci, intval($focus_id));
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

      // Replace foci string with serialized array
      function serializeTopics($table, $newTable, $index, $column) {
        global $wpdb;
        $rows = $wpdb -> get_results(
          "SELECT ".$index.", ".$column."
          FROM wordpress.".getTable($table).";"
        );

        $topics = array( 1 );

        foreach ( $rows as $row ) {
          $wpdb -> update(
            getTable($newTable),
            array(
              $column => serialize($topics)
            ),
            array(
              $index => $row -> $index
            )
          );
        }
      }

      // Convert existing problems to attempts
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

      // Returns a focus name from the id
      function getFocusName($id){
        global $wpdb;
        return $wpdb -> get_results("
          SELECT name
          FROM ".getTable('pg_foci')."
          WHERE focus_id = ".intval($id)."
        ;")[0] -> name;
      }

      // Convert an array of foci to names
      function getFocusNames($ids){
        $focus_names = [];
        foreach( $ids as $id ) {
          array_push($focus_names, getFocusName($id));
        }
        return $focus_names;
      }

      // Returns the id of a focus from the name
      function getFocusId($name){
        global $wpdb;
        return intval($wpdb -> get_results("
          SELECT focus_id
          FROM ".getTable('pg_foci')."
          WHERE name = '".esc_sql($name)."'
        ;")[0] -> focus_id);
      }

      // Convert an array of foci names to ids
      function getFocusIds($names){
        $focus_ids = [];
        foreach( $names as $name ) {
          array_push($focus_ids, getFocusId($name));
        }
        return $focus_ids; 
      }

      // Returns a focus name from the id
      function getTopicName($id){
        global $wpdb;
        return $wpdb -> get_results("
          SELECT name
          FROM ".getTable('pg_topics')."
          WHERE topic_id = ".intval($id)."
        ;")[0] -> name;
      }

      // Convert an array of topics to names;
      function getTopicNames($ids){
        $topic_names = [];
        foreach( $ids as $id ) {
          array_push($topic_names, getTopicName($id));
        }
        return $topic_names; 
      }

      // Returns the id of a focus from the name
      function getTopicId($name){
        global $wpdb;
        return intval($wpdb -> get_results("
          SELECT topic_id
          FROM ".getTable('pg_topics')."
          WHERE name = '".esc_sql($name)."'
        ;")[0] -> topic_id);
      }

      // Convert an array of foci names to ids
      function getTopicIds($names){
        $topic_ids = [];
        foreach( $names as $name ) {
          array_push($topic_ids, getTopicId($name));
        }
        return $topic_ids; 
      }

      function getUserStats($id){
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

        // Get attempts
        $attempts = $wpdb -> get_results("
          SELECT *
          FROM ".getTable('pg_user_attempts')."
          WHERE user_id = ".$id."
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

        // Loop through and calculate stats
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

              // Increment focus xp based on difficulty and streak (topic and overall xp is adedd)
              // 15% bonus if xp is streak is a multiple of 5
              $base_xp = $problem_info -> difficulty * ( 4 - count($problem) );
              if( 
                intval($all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak']) > 0 &&
                intval($all_stats['topic_stats'][$topic]['focus_stats'][$focus]['streak']) % 5 == 0
              )
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['xp'] += $base_xp * 1.15;
              else
                $all_stats['topic_stats'][$topic]['focus_stats'][$focus]['xp'] += $base_xp;

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
        $total_xp = 0;
        foreach ( $all_stats['topic_stats'] as $topic_id => $topic_stat ){
          $topic_xp = 0;
          // Loop through each focus in the topic
          foreach ( $topic_stat['focus_stats'] as $focus_id => $focus_stat ){
            $topic_xp += $focus_stat['xp'];
          }
          $topic_stat[$topic_id]['xp'] = $topic_xp;
        }
        $all_stats['xp'] = $total_xp;
        return $all_stats;
      }

      // Test functions endpoint
      if($GLOBALS['DEBUG']){
        global $wpdb;
        $wpdb -> show_errors();
        register_rest_route('physics_genie', 'test', array(
          'methods' => 'GET',
          'callback' => function($request){
              // Update main_focus in pg_problems
              // updateFocus('pg_problems', 'pg_problems_new', 'problem_id', 'main_focus');
            //convertAttempts('pg_user_problems', 'pg_user_attempts');
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

          $data -> contributor = current_user_can('administrator') || current_user_can('editor') || current_user_can('contributor');

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

          // Find a new problem if the user does not have a current problem
          if ( $wpdb -> get_results("
              SELECT curr_problem
              FROM ".getTable('pg_users')."
              WHERE user_id = ".get_current_user_id()."
            ;")[0] -> curr_problem === null) {

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

            $problem = $wpdb -> get_results("
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
                  (SELECT DISTINCT problem_id
                   FROM wordpress.".getTable('pg_user_attempts')."
                   WHERE user_id = ".get_current_user_id().")
              ORDER BY RAND()
              LIMIT 1
              ;");

            if (count($problem) == 0)
              return null;
            else {
              $problem = $problem[0];
              // Set the current problem to the new problem
              $wpdb -> update(
                getTable('pg_users'),
                array(
                  'curr_problem' => $problem -> problem_id
                ),
                array(
                  'user_id' => get_current_user_id()
                ),
                null,
                array('%d')
              );
            }
          } else {
            // Return the current problem if it exists
            $problem = $wpdb->get_results("
              SELECT * FROM ".getTable('pg_problems')."
              WHERE problem_id = (SELECT curr_problem FROM ".getTable('pg_users')."
              WHERE user_id  = ".get_current_user_id().")
            ;")[0];
          }

          // Convert other_foci to names
          $problem -> other_foci = getFocusNames( unserialize( $problem -> other_foci ) );

          // Convert topic and main_focus to names
          $problem -> topic = getTopicName( $wpdb -> get_results("
            SELECT topic
            FROM ".getTable("pg_foci")."
            WHERE focus_id = ".($problem -> main_focus)."
          ;")[0] -> topic );
          $problem -> main_focus = getFocusName( $problem -> main_focus );

          return json_encode($problem);
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

          $problem = $wpdb->get_results("
            SELECT *
            FROM ".getTable('pg_problems')."
            WHERE ".getTable('pg_problems').".problem_id = ".$data['problem']."
          ;")[0];

          // Convert other_foci to names
          $problem -> other_foci = getFocusNames( unserialize( $problem -> other_foci ) );

          // Convert topic and main_focus to names
          $problem -> topic = getTopicName( $wpdb -> get_results("
            SELECT topic
            FROM ".getTable("pg_foci")."
            WHERE focus_id = ".($problem -> main_focus)."
          ;")[0] -> topic );
          $problem -> main_focus = getFocusName( $problem -> main_focus );

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

          $problems = $wpdb -> get_results("
            SELECT *
            FROM ".getTable('pg_problems')."
            WHERE submitter = ".(get_current_user_id())."
            ORDER BY problem_id DESC
          ;");

          if (get_current_user_id() === 1 || get_current_user_id() === 6 || get_current_user_id() === 16) {
            $problems = $wpdb -> get_results("
              SELECT *
              FROM ".getTable('pg_problems')."
              ORDER BY problem_id DESC
            ;");
          }

          foreach( $problems as $problem ){
            // Convert other_foci to names
            $problem -> other_foci = getFocusNames( unserialize( $problem -> other_foci ) );
            // Convert topic and main_focus to names
            $problem -> topic = getTopicName( $wpdb -> get_results("
              SELECT topic
              FROM ".getTable("pg_foci")."
              WHERE focus_id = ".($problem -> main_focus)."
            ;")[0] -> topic );
            $problem -> main_focus = getFocusName( $problem -> main_focus );
          }
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

          // Convert curr_topics to names
          $data -> setup -> curr_topics = getTopicNames( unserialize( $data -> setup -> curr_topics ) );

          // Convert curr_foci to names
          $data -> setup -> curr_foci = getFocusNames( unserialize( $data -> setup -> curr_foci ) );

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
        'callback' => function() {
          $all_stats = getUserStats(get_current_user_id());

          // Create the response object
          $topic_stats = [];
          $total_xp = 0;
          // Loop through each topic
          foreach ( $all_stats['topic_stats'] as $topic_id => $topic_stat ){
            // Set the topic name
            $topic_name = getTopicName($topic_id);
            $topic_stat['topic'] = $topic_name;
            $focus_stats = [];
            $topic_xp = 0;

            // Loop through each focus in the topic
            foreach ( $topic_stat['focus_stats'] as $focus_id => $focus_stat ){
              // Set the focus name
              $focus_name = getFocusName($focus_id);
              $focus_stat['focus'] = $focus_name;
              // Invert losestreak so the result is positive
              $focus_stat['longest_losestreak'] = - $focus_stat['longest_losestreak'];
              // Add xp
              $topic_xp += $focus_stat['xp'];
              array_push($focus_stats, $focus_stat);
            }

            $total_xp += $topic_xp;
            // Add the focus stats for the topic
            $topic_stat['focus_stats'] = $focus_stats;
            // Invert the losestreak
            $topic_stat['longest_losestreak'] = - $topic_stat['longest_losestreak'];
            // Set the total topic xp
            $topic_stat['xp'] = $topic_xp;
            array_push($topic_stats, $topic_stat);
          }

          // Add the complete stats for the topic
          $all_stats['topic_stats'] = $topic_stats;
          // Set the total xp
          $all_stats['xp'] = $total_xp;
          // Invert losestreak
          $all_stats['longest_losestreak'] = - $all_stats['longest_losestreak'];

          return json_encode($all_stats);
        },
        'permission_callback' => '__return_true'
      ));

      /**
       * @api {get} /leaderboard Get leaderboard stats
       * @apiName GetLeaderboardStats
       * @apiGroup User 
       * @apiDescription Gets leaderboard statistics based on type, time, topic, focus, and difficulty.
       * @apiParam {String} type Either xp, correct, streak, or submitted
       * @apiParam {String} time Either week, month, or all
       * @apiParam {String} topic Either the topic name or all
       * @apiParam {String} focus Either the focus name or all
       * @apiParam {Number} difficulty The difficulty level 
       *
       */
      register_rest_route('physics_genie', 'leaderboard', array(
        'methods' => 'GET',
        'callback' => function($request_data) {
          return getUserStats(1);
          global $wpdb;
          $json = json_decode($request_data -> get_body());
          $json -> type = $json -> type ?? 'xp';
          $json -> time = $json -> time ?? 'all';
          $json -> topic = $json -> topic ?? 'all';
          $json -> focus = $json -> focus ?? 'all';
          $json -> difficulty = $json -> difficulty ?? 'all';

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

          $topics = $wpdb -> get_results("
            SELECT topic_id, name 
            FROM ".getTable('pg_topics')."
          ;");

          $users = $wpdb -> get_results("
            SELECT user_id
            FROM ".getTable('pg_users')."
          ;");

          $all_user_stats = [];

          foreach ( $users as $user ){
            $user_stats = [];
            foreach ( $topics as $topic ) {
              $foci = $wpdb -> get_results("
                SELECT focus_id, topic
                FROM ".getTable('pg_foci')."
                WHERE topic = ".$topic -> topic_id."
              ;");

              $user_stats['topic_stats'][$topic -> topic_id] = $blank_stats;
              foreach ( $foci as $focus ) {
                $user_stats['topic_stats'][$topic -> topic_id]['focus_stats'][$focus -> focus_id] = $blank_stats;
              }
            }
            $all_user_stats[$user -> user_id] = $user_stats;
          }

          // Get attempts
          $attempts = $wpdb -> get_results("
            SELECT *
            FROM ".getTable('pg_user_attempts')."
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

          if( $json -> time === 'any' )
            $min_date = 0;
          else if ( $json -> time === 'week' )
            $min_date = date_create(date('Y-m-d H:i:s')) -> modify('-7 days') -> format('Y-m-d H:i:s');
          else if ( $json -> time === 'month' )
            $min_date = date_create(date('Y-m-d H:i:s')) -> modify('-30 days') -> format('Y-m-d H:i:s');

          foreach ( $problems as $problem_id => $problem  ) {
            if ( end($problem) -> date_attempted > $min_date ){
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

              $user_id = $problem[0] -> user_id;

              // Loop through the problem attempts until a correct answer or a give up
              $break = False;
              $user_attempts = [];
              for( $i = 0; $i < count($problem); $i++ ) {
                if($problem[$i] -> give_up === "1"){
                  $completed = True;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['give_ups'] ++;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['give_ups'] ++;
                  $all_user_stats[$user_id]['give_ups'] ++;
                }

                if($problem[$i] -> correct === "1"){
                  $correct = True;
                  $completed = True;
                }

                // Increase attempts if not a give up
                if( $problem[$i] -> give_up === "0" ){
                  $completed_attempts ++;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_attempts'] ++;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['num_attempts'] ++;
                  $all_user_stats[$user_id]['num_attempts'] ++;
                }
              }

              // Set the problem as completed if all attempts have been used
              if( $completed === False && count($problem) >= 3 )
                $completed = True;

              // Set the problem as completed if all attempts have been used
              if( $completed === False && count($problem) >= 3 )
                $completed = True;

              // Increase presented problems
              $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_presented'] ++;
              $all_user_stats[$user_id]['topic_stats'][$topic]['num_presented'] ++;
              $all_user_stats[$user_id]['num_presented'] ++;

              if( $completed ) {
                // Increment completed attempts
                $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['completed_attempts'] += $completed_attempts;
                $all_user_stats[$user_id]['topic_stats'][$topic]['completed_attempts'] +- $completed_attempts;
                $all_user_stats[$user_id]['completed_attempts'] += $completed_attempts;

                // Increment completed problems
                $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_completed'] ++;
                $all_user_stats[$user_id]['topic_stats'][$topic]['num_completed'] ++;
                $all_user_stats[$user_id]['num_completed'] ++;

                if( $correct ){
                  // Increment correct problems
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_correct'] ++;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['num_correct'] ++;
                  $all_user_stats[$user_id]['num_correct'] ++;

                  // Increment focus xp based on difficulty and streak (topic and overall xp is adedd)
                  // 15% bonus if xp is streak is a multiple of 5
                  $base_xp = $problem_info -> difficulty * ( 4 - count($problem) );
                  if( 
                    intval($all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak']) > 0 &&
                    intval($all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak']) % 5 == 0
                  )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['xp'] += $base_xp * 1.15;
                  else
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['xp'] += $base_xp;

                  // Set the focus streak stats
                  if( $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] >= 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] ++;
                  elseif( $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] < 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] = 1;

                  // Set the topic streak stats
                  if( $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] >= 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] ++;
                  elseif( $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] < 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] = 1;

                  // Set the overall streak stats
                  if( $all_user_stats[$user_id]['streak'] >= 0 )
                    $all_user_stats[$user_id]['streak'] ++;
                  elseif( $all_user_stats[$user_id]['streak'] < 0 )
                    $all_user_stats[$user_id]['streak'] = 1;

                } else {
                  // Increment incorrect problems
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_incorrect'] ++;
                  $all_user_stats[$user_id]['topic_stats'][$topic]['num_incorrect'] ++;
                  $all_user_stats[$user_id]['num_incorrect'] ++;
   
                  // Set the focus streak stats
                  if( $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] <= 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] --;
                  elseif( $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] > 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'] = -1;

                  // Set the topic streak stats
                  if( $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] <= 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] --;
                  elseif( $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] > 0 )
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'] = -1;

                  // Set the overall streak stats
                  if( $all_user_stats[$user_id]['streak'] <= 0 )
                    $all_user_stats[$user_id]['streak'] --;
                  elseif( $all_user_stats[$user_id]['streak'] > 0 )
                    $all_user_stats[$user_id]['streak'] = -1;
               }

                // Set the focus longest winstreak
                $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['longest_winstreak'] = 
                  max(
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['longest_winstreak'],
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'],
                  );

                // Set the focus longest losestreak
                $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['longest_losestreak'] = 
                  min(
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['longest_losestreak'],
                    $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['streak'],
                  );

                // Set the topic longest winstreak
                $all_user_stats[$user_id]['topic_stats'][$topic]['longest_winstreak'] =
                  max(
                    $all_user_stats[$user_id]['topic_stats'][$topic]['longest_winstreak'],
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'],
                  );

                // Set the topic longest losestreak
                $all_user_stats[$user_id]['topic_stats'][$topic]['longest_losestreak'] =
                  min(
                    $all_user_stats[$user_id]['topic_stats'][$topic]['longest_losestreak'],
                    $all_user_stats[$user_id]['topic_stats'][$topic]['streak'],
                  );

                // Set the overall longest winstreak
                $all_user_stats[$user_id]['longest_winstreak'] = 
                  max(
                    $all_user_stats[$user_id]['longest_winstreak'],
                    $all_user_stats[$user_id]['streak'],
                  );

                // Set the overall longest losestreak
                $all_user_stats[$user_id]['longest_losestreak'] = 
                  min(
                    $all_user_stats[$user_id]['longest_losestreak'],
                    $all_user_stats[$user_id]['streak'],
                  );

                // Set the average attempts
                $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['avg_attempts'] = 
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['completed_attempts']/
                  $all_user_stats[$user_id]['topic_stats'][$topic]['focus_stats'][$focus]['num_completed'];

                $all_user_stats[$user_id]['topic_stats'][$topic]['avg_attempts'] = 
                  $all_user_stats[$user_id]['topic_stats'][$topic]['completed_attempts']/
                  $all_user_stats[$user_id]['topic_stats'][$topic]['num_completed'];

                $all_user_stats[$user_id]['avg_attempts'] = 
                  $all_user_stats[$user_id]['completed_attempts']/
                  $all_user_stats[$user_id]['num_completed'];
              }
            }
          }

          foreach($all_user_stats as $user_stat){
            $total_xp = 0;
            // Loop through each topic
            foreach ( $user_stat['topic_stats'] as $topic_id => $topic_stat ){
              $topic_xp = 0;
              // Loop through each focus in the topic
              foreach ( $topic_stat['focus_stats'] as $focus_id => $focus_stat ){
                $topic_xp += $focus_stat['xp'];
              }
              $topic_stat[$topic_id]['xp'] = $topic_xp;
            }
            $user_stat['xp'] = $total_xp;
          }

          return $all_user_stats['1'];

          if( $json -> type === 'xp' ){
            if( $json -> topic === 'all' ){
              uasort($all_user_stats, function ($a, $b) {
                return $a -> user_attempt_id <=> end($b) -> user_attempt_id;
              });
            } else {
            }
            if( $json -> focus === 'all')
            uasort($all_user_stats, function ($a, $b) {
              return end($a) -> user_attempt_id <=> end($b) -> user_attempt_id;
            });
          } else if( $json -> type === 'correct' ) {
          } else if( $json -> type === 'streak' ) {
          
          } else if( $json -> type === 'submitted' ) {
          }

          return $all_user_stats['6'];
          return $min_date;
          // return json_encode($data);
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
          $json = json_decode($request_data -> get_body());
          $user_data = array(
            'user_login'    => $json -> username,
            'user_email'    => $json -> email,
            'user_pass'     => $json -> password,
            'first_name'    => "",
            'last_name'     => "",
            'nickname'      => "",
          );

          $user_id = wp_insert_user( $user_data );

          if (is_wp_error($user_id)) {
            return json_encode($user_id -> get_error_messages());
          }

          global $wpdb;
          $wpdb -> insert(
            getTable('pg_users'),
            array(
              'user_id' => $user_id,
              'curr_diff' => 1,
              'curr_topics' => serialize(
                array(
                  1
                )
              ),
              'curr_foci' => serialize(
                array(
                  8,
                  9,
                )
              ),
              'calculus' => 1,
            )
          );

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
          $json = json_decode($request_data -> get_body());
          $user_data = get_user_by('email', $json -> email);

          $user_login = $user_data -> user_login;
          $user_email = $user_data -> user_email;
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
          $wpdb -> insert(
            getTable('pg_problem_errors'),
            array(
              'user_id' => get_current_user_id(),
              'problem_id' => $json -> problem_id,
              'error_location' => $json -> error_location,
              'error_type' => $json -> error_type,
              'error_message' => $json -> error_message,
            )
          );

          return $wpdb -> insert_id;
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
          $json = json_decode($request_data -> get_body());

          // Convert other_foci to a serialized integer array
          if( $json -> other_foci === null )
            $other_foci = null;
          else
            $other_foci = serialize(getFocusIds($json -> other_foci));

          global $wpdb;
          $wpdb -> insert(
            getTable('pg_problems'),
            array(
              'problem_text' => $json -> problem_text,
              'diagram' => $json -> diagram,
              'answer' => $json -> answer,
              'must_match' => $json -> must_match,
              'error' => floatval($json -> error),
              'solution' => $json -> solution,
              'solution_diagram' => $json -> solution_diagram,
              'hint_one' => $json -> hint_one,
              'hint_two' => $json -> hint_two,
              'source' => intval($json -> source),
              'number_in_source' => $json -> number_in_source,
              'submitter' => get_current_user_id(),
              'difficulty' => intval($json -> difficulty),
              'calculus' => $json -> calculus,
              'main_focus' => getFocusId($json -> main_focus),
              'other_foci' => $other_foci,
              'date_added' => date('Y-m-d')
            )
          );

          return $wpdb -> insert_id;
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
          $json = json_decode($request_data -> get_body());

          global $wpdb;

          $wpdb -> insert(getTable('pg_sources'),
            array(
              'category' => $json -> category,
              'author' => $json -> author,
              'source' => $json -> source
            )
          );

          return $wpdb -> insert_id;
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
       * @apiParam {String} student_answer The student's answer for the attempt
       * @apiParam {String} correct Whether or not the problem was correct ("true" if correct, "false" otherwise).
       *
       * @apiSuccess {Number} complete Returns true if the attempt is correct or is the final attempt.
       * @apiSuccess {Boolean} correct Returns true if the attempt is correct
       */
      register_rest_route('physics_genie', 'submit-attempt', array(
        'methods' => 'POST',
        'callback' => function($request_data) {
          $json = json_decode($request_data -> get_body());
          global $wpdb;

          $wpdb -> insert(
            getTable('pg_user_attempts'),
            array(
              'user_id' => get_current_user_id(),
              'problem_id' => $json -> problem_id,
              'student_answer' => $json -> student_answer,
              'correct' => $json -> correct,
              'give_up' => $json -> give_up,
              'date_attempted' => date('Y-m-d H:i:s')
            )
          );

          $wpdb -> update(
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

          $response = (object)[];

          $response -> complete = FALSE;

          if( $json -> correct === TRUE )
            $response -> complete = TRUE;

          $attempts = $wpdb -> get_results("
            SELECT COUNT(user_attempt_id)
            FROM wordpress.".getTable('pg_user_attempts')."
            WHERE user_id = ".get_current_user_id()."
              AND problem_id = ".$json -> problem_id."
          ;")[0] -> {'COUNT(user_attempt_id)'};

          if( $attempts >= 3 )
            $response -> complete = TRUE;

          $response -> correct = $json -> correct;

          return $response;
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
          $json = json_decode($request_data -> get_body());
          return $this -> CallAPI($json["method"], $json["url"], $json["data"]);
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
          return $wpdb -> update(
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
          $json = json_decode($request_data -> get_body());
          global $wpdb;
          return $wpdb -> update(
            getTable('users'),
            array(
              'user_login' => $json -> name,
              'user_nicename' => $json -> name
            ),
            array(
              'ID' => get_current_user_id()
            ),
            null,
            array('%d')
          );
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
          $json = json_decode($request_data -> get_body());
          global $wpdb;
          return $wpdb -> update(
            getTable('pg_users'),
            array(
              'curr_diff' => intval($json -> curr_diff),
              'curr_topics' => serialize(
                getTopicIds($json -> curr_topics)
              ),
              'curr_foci' => serialize(
                getFocusIds($json -> curr_foci)
              ),
              'calculus' => $json -> calculus
            ),
            array(
              'user_id' => get_current_user_id()
            ),
            array('%d', '%s', '%s', '%d'),
            array('%d')
          );
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
          $json = json_decode($request_data -> get_body());

          // Convert other_foci to a serialized integer array
          if( $json -> other_foci === null )
            $other_foci = null;
          else
            $other_foci = serialize(getFocusIds($json -> other_foci));

          global $wpdb;
          return $wpdb -> update(getTable('pg_problems'), 
          array(
            'problem_text' => $json -> problem_text,
            'diagram' => $json -> diagram,
            'answer' => $json -> answer,
            'must_match' => $json -> must_match,
            'error' => floatval($json -> error),
            'solution' => $json -> solution,
            'solution_diagram' => $json -> solution_diagram,
            'hint_one' => $json -> hint_one,
            'hint_two' => $json -> hint_two,
            'source' => intval($json -> source),
            'number_in_source' => $json -> number_in_source,
            'submitter' => get_current_user_id(),
            'difficulty' => intval($json -> difficulty),
            'calculus' => $json -> calculus,
            'main_focus' => getFocusId($json -> main_focus),
            'other_foci' => $other_foci,
            'date_added' => date('Y-m-d')
          ),
          array(
            'problem_id' => $json -> problem_id
          ),
          null,
          array('%d'));
        },
        'permission_callback' => '__return_true'
      ));

    });
  }

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
