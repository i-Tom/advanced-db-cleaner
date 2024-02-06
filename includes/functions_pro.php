<?php

/** Prepare args: current page number, order by, LIMIT, OFFSET...
	$default_order_by contains the default column for ORDER BY
	$search_key is the column name in which we will search if the user choose the first radio in the form
	$search_value is the column name in which we will search if the user choose the second radio in the form
	args by WP: paged, order_by, order,
	args be aDBc: per_page, s, in
 */
function aDBc_get_search_sql_arg( $search_in_key, $search_in_value) {

		// Prepare LIKE sql clause
		$search_like = "";

		if ( ! empty( $_GET['s'] ) && trim( $_GET['s'] ) != "" ) {

			$search = esc_sql( sanitize_text_field( $_GET['s'] ) );
			$in 	= $search_in_key;

			if ( ! empty( $_GET['in'] ) ) {

				$in = ($_GET['in'] == "key") ? $search_in_key : $search_in_value;

			}

			$search_like = " AND $in LIKE '%{$search}%'";

		}

		return $search_like;
}

/***********************************************************************************
* This function filters the array containing results according to users args
***********************************************************************************/
function aDBc_filter_results_in_all_items_array(&$aDBc_all_items, $aDBc_tables_name_to_optimize, $aDBc_tables_name_to_repair){

	if(function_exists('is_multisite') && is_multisite()){

		// Filter according to sites
		if(!empty($_GET['site'])){
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if($site_id != $_GET['site']){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to search
		if(!empty($_GET['s']) && trim($_GET['s']) != ""){
			$search = esc_sql(sanitize_text_field($_GET['s']));
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					$table_prefix_if_exists = empty($site_item_info['prefix']) ? "" : $site_item_info['prefix'];
					if(strpos($table_prefix_if_exists . $item_name, $search) === false){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to tables types (to optimize, to repair...)
		if(!empty($_GET['t_type']) && $_GET['t_type'] != "all"){
			$type = esc_sql($_GET['t_type']);
			if($type == 'optimize'){
				$array_names = $aDBc_tables_name_to_optimize;
			}else{
				$array_names = $aDBc_tables_name_to_repair;
			}
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if(!in_array($site_item_info['prefix'] . $item_name, $array_names)){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to autoload
		if(!empty($_GET['autoload']) && $_GET['autoload'] != "all"){
			$autoload_param = esc_sql($_GET['autoload']);
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if($site_item_info['autoload'] != $autoload_param){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to belongs_to
		if(!empty($_GET['belongs_to']) && $_GET['belongs_to'] != "all"){
			$belongs_to_param = esc_sql($_GET['belongs_to']);
			$names_to_delete = array();
			foreach($aDBc_all_items as $item_name => $item_info){
				$belongs_to_value = explode("(", $item_info['belongs_to'], 2);
				$belongs_to_value = trim($belongs_to_value[0]);
				$belongs_to_value = str_replace(" ", "-", $belongs_to_value);
				if($belongs_to_value != $belongs_to_param){
					array_push($names_to_delete, $item_name);
				}
			}
			// Loop over the names to delete and delete them for the array
			foreach($names_to_delete as $name){
				unset($aDBc_all_items[$name]);
			}
		}


	}else{

		// Prepare an array containing names of items to delete
		$names_to_delete = array();

		// Filter according to search parameter
		$filter_on_search = !empty($_GET['s']) && trim($_GET['s']) != "";
		if($filter_on_search){
			$search = esc_sql(sanitize_text_field($_GET['s']));
		}

		// Filter according to tables types (to optimize, to repair...)
		$filter_on_t_type = !empty($_GET['t_type']) && $_GET['t_type'] != "all";
		if($filter_on_t_type){
			$type = esc_sql($_GET['t_type']);
			if($type == "optimize"){
				$array_names = $aDBc_tables_name_to_optimize;
			}else{
				$array_names = $aDBc_tables_name_to_repair;
			}
		}

		// Filter according to autoload
		$filter_on_autoload = !empty($_GET['autoload']) && $_GET['autoload'] != "all";
		if($filter_on_autoload){
			$autoload_param = esc_sql($_GET['autoload']);
		}

		// Filter according to belongs_to
		$filter_on_belongs_to = !empty($_GET['belongs_to']) && $_GET['belongs_to'] != "all";
		if($filter_on_belongs_to){
			$belongs_to_param = esc_sql($_GET['belongs_to']);
		}

		foreach($aDBc_all_items as $item_name => $item_info){

			if($filter_on_search){
				$aDBc_prefix = empty($item_info['sites'][1]['prefix']) ? "" : $item_info['sites'][1]['prefix'];
				if(strpos($aDBc_prefix . $item_name, $search) === false){
					array_push($names_to_delete, $item_name);
				}
			}

			if($filter_on_t_type){
				if(!in_array($item_info['sites'][1]['prefix'] . $item_name, $array_names)){
					array_push($names_to_delete, $item_name);
				}
			}

			if($filter_on_autoload){
				if($item_info['sites'][1]['autoload'] != $autoload_param){
					array_push($names_to_delete, $item_name);
				}
			}

			if($filter_on_belongs_to){
				$belongs_to_value = explode("(", $item_info['belongs_to'], 2);
				$belongs_to_value = trim($belongs_to_value[0]);
				$belongs_to_value = str_replace(" ", "-", $belongs_to_value);
				if($belongs_to_value != $belongs_to_param){
					array_push($names_to_delete, $item_name);
				}
			}

		}

		// Loop over the names to delete and delete them for the array
		foreach($names_to_delete as $name){
			unset($aDBc_all_items[$name]);
		}

	}

}

/***************************************************************************************
 * This function returns the number of items that will be displayed in the current page
 ***************************************************************************************/
function aDBc_get_progress_bar_width(){
	
	// Check if the request is empty, if so, return
	if(!isset($_REQUEST['aDBc_item_type']) || empty($_REQUEST['aDBc_item_type'])) {
		return;
	}

	// Check if item type is valid
	$items_type = $_REQUEST['aDBc_item_type'];
	if ($items_type != "tasks" && $items_type != "options" && $items_type != "tables") {
		return;
	}

	// Get scan progress data from database
	$progress_scan_data = get_option("aDBc_temp_progress_scan_" . $items_type, "0:0");
	$progress_scan_data = explode(":", $progress_scan_data);

	$progress 		 = $progress_scan_data[0];
	$total_files 	 = $progress_scan_data[1];

	// Get files collection progress data
	$progress_files_preparation = get_option("aDBc_temp_progress_files_preparation_" . $items_type, "0:0");
	$progress_files_preparation = explode(":", $progress_files_preparation);

	$collected_files = $progress_files_preparation[0];
	$scanned_files 	 = $progress_files_preparation[1];

	// Get the current scan step from database
	$current_scan_step = get_option("aDBc_temp_current_scan_step_" . $items_type, "");

	$status = array(
		'aDBc_progress' 	=> $progress,
		'aDBc_total_items' 	=> $total_files,
		'aDBc_collected_files' 	=> $collected_files,
		'aDBc_scanned_files' 	=> $scanned_files,
		'aDBc_current_scan_step' => $current_scan_step
		);

	echo json_encode($status);

	wp_die();
}

/************************************************************************************
* Searches for any item name in the "$items_to_search_for" in all files of WordPress
************************************************************************************/
function aDBc_new_run_search_for_items(){

	// check if the upload folder exists and writable
	if (!is_dir(ADBC_UPLOAD_DIR_PATH_TO_ADBC) || !is_writable(ADBC_UPLOAD_DIR_PATH_TO_ADBC)) {
		update_option("aDBc_permission_adbc_folder_needed", "yes");
		return;
	}

    // The $_REQUEST contains all the data sent via ajax
	// Test if the request is empty, if so, return
	if (!isset($_REQUEST) || empty($_REQUEST)) {
		return;
	}

	// $items_type is sent by both buttons (normal scan button and "apply" button)
	$items_type = $_REQUEST['aDBc_item_type'];

	// Check if item type is valid
	if ($items_type != "tasks" && $items_type != "options" && $items_type != "tables") {
		return;
	}

	// Test if the user wants to stop the scan
	if (aDBc_stop_scan($items_type)) {
		wp_die();
	}
	
	// Test if there is a current scan running. If so, wait until it finishes and die so ajax can load the results
	if (get_option("aDBc_temp_currently_scanning_" . $items_type)) {
		while (get_option("aDBc_temp_currently_scanning_" . $items_type)) {
			sleep(2);
			wp_cache_delete("aDBc_temp_currently_scanning_" . $items_type, 'options');
		}
		wp_die();
	}
	
	// Set the unique scan flag to prevent other scans from running
	update_option("aDBc_temp_currently_scanning_" . $items_type, 1, "no");

	/***************************************************************************************************************************************
	* This function can be called by 3 different ways:
	* (1) The normal scan button, (2) the "Apply" button represented by "#doaction" and "#doaction2" and (3) page reload 
	* In the case the call comes from (1), we will scan all items from scratch, unless the user selected to scan only "uncategorized" items
	* In case the call comes from (2), we will scan only checked items and append results to the results file
	* In case the call comes from (3), we will continue the scan from where it stopped
	****************************************************************************************************************************************/
	
	// Get the iteration from the database: either 0, 1 or 2. Default is 0
	global $aDBc_iteration;
	$aDBc_iteration 			= get_option("aDBc_temp_last_iteration_" . $items_type);
	$aDBc_iteration 			= $aDBc_iteration === false ? 0 : $aDBc_iteration;
	
	// Update the current scan step in database
	$current_scan_step = $aDBc_iteration == 0 ? 1 : 2;
	update_option("aDBc_temp_current_scan_step_" . $items_type, $current_scan_step, "no");

	// First thing to do, set the scan progress to the saved values in database if exists else set it to 0
	$progress_scan_data = get_option("aDBc_temp_progress_scan_" . $items_type, "0:0");
	if ($progress_scan_data === "0:0") {
		update_option("aDBc_temp_progress_scan_" . $items_type, "0:0", "no");
	}

	// First thing to do, set the files preparation progress to the saved values in database if exists else set it to 0
	$progress_files_preparation = get_option("aDBc_temp_progress_files_preparation_" . $items_type, "0:0");
	if ($progress_files_preparation === "0:0") {
		update_option("aDBc_temp_progress_files_preparation_" . $items_type, "0:0", "no");
	}

	/**********************************************************************************************************************
	* Prepare all paths to files that will be used during the process
	***********************************************************************************************************************/
	$path_file_categorization 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . ".txt";
	$path_file_to_categorize 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_to_categorize.txt";
	$path_file_all_php_files	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths_{$items_type}.txt";

	// Contains the global list of items we will scan
	global $items_to_search_for;
	$items_to_search_for = array();

	// This global variable is used by the shutdown function when timeout. We send the item type to that function via a global variable
	global $item_type_for_shutdown;
	$item_type_for_shutdown = $items_type;

	// Stores the current line that should be processed in "to_categorize.txt". Default value is 1, unless a timeout has occured
	global $aDBc_item_line;
	$item_line 			= get_option("aDBc_temp_last_item_line_" . $items_type);
	$aDBc_item_line 	= empty($item_line)? 1 : $item_line;

	// Stores the current line that have been reached in "all_files_paths.txt". Default value is 1, unless a timeout has occured
	global $aDBc_file_line;
	$file_line 			= get_option("aDBc_temp_last_file_line_" . $items_type);
	$aDBc_file_line 	= empty($file_line)? 1 : $file_line;

	// Stores if the search has finished or not to be used in shutdown function. When this function is called, always set this to "no"
	global $aDBc_search_has_finished;
	$aDBc_search_has_finished = "no";

	// Create an array that will hundle already categorized items
	$items_already_categorized = array();

	// Save total files found
	$saved_total = get_option("aDBc_temp_total_files_" . $items_type);
	$aDBc_total_files = empty($saved_total) ? 0 : $saved_total;

	// $aDBc_items_to_scan is an array sent only by the "apply" button
	global $aDBc_items_to_scan;
	// Try to get it from database first to continue the scan from where it stopped
	$aDBc_items_to_scan = get_option("aDBc_temp_items_to_scan_" . $items_type);
	if (empty($aDBc_items_to_scan)) {
		$aDBc_items_to_scan = isset($_REQUEST['aDBc_items_to_scan']) ? $_REQUEST['aDBc_items_to_scan'] : "";
	}	

	// $aDBc_scan_type is a string sent only by the "Scan" button. It may be "scan_all" or "scan_uncategorized"
	global $aDBc_scan_type;
	// Try to get it from database first to continue the scan from where it stopped
	$aDBc_scan_type = get_option("aDBc_temp_scan_type_" . $items_type);
	if (empty($aDBc_scan_type)) {
		$aDBc_scan_type = isset($_REQUEST['aDBc_scan_type']) ? $_REQUEST['aDBc_scan_type'] : "";
	}
	
	
	/***********************************************************************************************************************
	* This section prepares files to run a new search. If aDBc_temp_last_iteration_ already exists, this means that we have
	* started a search and not finish it yet, just skip this section then
	***********************************************************************************************************************/
	if ( $aDBc_iteration == 0) {

		/***********************************************************************************************************************
		 * This section prepares files to run a new search.
		 * **********************************************************************************************************************/
		
		// Refresh "all_files_paths_{items_type}.txt" containing all wordpress php files paths
		aDBc_refresh_and_create_php_files_paths($items_type);

		// Count total files number
		$total_files_urls = fopen($path_file_all_php_files, "r");
		while(($item = fgets($total_files_urls)) !== false){
			$aDBc_total_files++;
		}
		fclose($total_files_urls);

		// To calculate progress of research, we will base on total files x2. Because we have 2 iterations in which we go through all files.
		update_option("aDBc_temp_total_files_" . $items_type, ($aDBc_total_files * 2), "no");
		$aDBc_total_files = $aDBc_total_files * 2;

		/*-----------------------------------------------------------------------
		Load items that will be scanned
		---------------------------------------------------------------------- */

		// If $aDBc_items_to_scan is empty => the call comes from "scan" blue button. Otherwise => from "apply" button
		if(empty($aDBc_items_to_scan)){

			// If the user wants to scan all items or uncategorized ones
			if($aDBc_scan_type == "scan_all" || $aDBc_scan_type == "scan_uncategorized"){
				// First, we prepare an array containing all items
				switch($items_type){
					case 'tasks' :
						$items_to_search_for = aDBc_get_all_scheduled_tasks();
						break;
					case 'options' :
						$items_to_search_for = aDBc_get_all_options();
						break;
					case 'tables' :
						$items_to_search_for = aDBc_get_all_tables();
						break;
				}

				// filter adbc temp options if items_type is options
				if ($items_type == "options") {
					$adbc_temp_options = aDBc_get_adbc_temp_options($items_type);
					foreach ($items_to_search_for as $key => $value) {
						if (in_array($key, $adbc_temp_options)) {
							// delete this item from items_to_search_for
							unset($items_to_search_for[$key]);
						}
					}
				}

				// If the user wants to scan only uncategorized items, then unset categorized ones from the array
				if($aDBc_scan_type == "scan_uncategorized"){

					// Test if results file exists
					if(file_exists($path_file_categorization)){
						// Get all categorized items and unset them
						$results_file = fopen($path_file_categorization, "r");
						while(($item = fgets($results_file)) !== false){
							$item_name = explode(":", trim($item), 2);
							$item_name = str_replace("+=+", ":", $item_name[0]);
							unset($items_to_search_for[$item_name]);
						}
						fclose($results_file);
					}
				}
			}

		}else{

			// Loop over items to scan and add them to the array. Delete duplicated items to prevent adding duplicates in case of MU
			foreach($aDBc_items_to_scan as $item_to_scan){
				$columns = explode("|", trim($item_to_scan));
				$item_name = trim($columns[1]);
				if(!array_key_exists($item_name, $items_to_search_for)){
					$items_to_search_for[$item_name] = array('belongs_to' => '', 'maybe_belongs_to' => '');
				}
			}
		}

		// Get the list of WP core items that are already categorized by default
		if($items_type == "tasks"){
			$aDBc_core_items = aDBc_get_core_tasks();
		}else if($items_type == "options"){
			$aDBc_core_items = aDBc_get_core_options();
		}else if($items_type == "tables"){
			$aDBc_core_items = aDBc_get_core_tables();
		}

		// Get the list of the ADBC core active options names
		$aDBc_my_plugin_core_items = aDBc_get_ADBC_options_and_tasks_names();

		// If the user wants to run full scan, delete the results file first. Otheriwse, just append results
		if($aDBc_scan_type == "scan_all" && file_exists($path_file_categorization)){
			unlink($path_file_categorization);
		}

		// Open the file that will contains scan results. If it does not exist, it will be created
		$myfile_categorization = fopen($path_file_categorization, "a");

		// Create the file named $items_type."to_categorize.txt" containing all $items_type to categorize while searching for orphans. Then fill it
		$myfile_to_categorize = fopen($path_file_to_categorize, "a");

		// Add all items to $myfile_to_categorize + categorize directly in core
		foreach($items_to_search_for as $aDBc_item => $aDBc_info){

			fwrite($myfile_to_categorize, $aDBc_item . "\n");
			// If the item belong to core, categorize it directly
			if(in_array($aDBc_item, $aDBc_core_items)){

				fwrite($myfile_categorization, str_replace(":", "+=+", $aDBc_item) . ":w:w" . "\n");
				array_push($items_already_categorized, $aDBc_item);
				// We fill belongs to to prevent processing this item later since it is already categorized
				$items_to_search_for[$aDBc_item]['belongs_to'] = "ok";

			// If the item belong to ADBC plugin, categorize it directly
			}else if(in_array($aDBc_item, $aDBc_my_plugin_core_items)){

				fwrite($myfile_categorization, str_replace(":", "+=+", $aDBc_item) . ":advanced-database-cleaner-pro:p" . "\n");
				array_push($items_already_categorized, $aDBc_item);
				// We fill belongs to to prevent processing this item later since it is already categorized
				$items_to_search_for[$aDBc_item]['belongs_to'] = "ok";

			}
		}

		fclose($myfile_categorization);
		fclose($myfile_to_categorize);

		$aDBc_iteration = 1;
		update_option("aDBc_temp_current_scan_step_" . $items_type, 2, "no");

	} else {

		/**********************************************************************************************************************
		* If we continue after timeout, we will do some adjustments
		***********************************************************************************************************************/

		// Get the list of items that should be scanned
		// xxx always test to see fopen has returned resource! otherwise false is returned!
		$myfile_to_categorize = fopen( $path_file_to_categorize, "r" );

		while ( ( $item = fgets( $myfile_to_categorize ) ) !== false ) {

			$item = trim( $item );

			if ( ! empty( $item ) )

				$items_to_search_for[$item] = array('belongs_to' => '', 'maybe_belongs_to' => '');
		}

		fclose($myfile_to_categorize);

		// In $items_to_search_for, mark all items that are already categorized as ok to save time in iteration 1
		$myfile_categorization = fopen( $path_file_categorization, "r" );

		while ( ( $item = fgets( $myfile_categorization ) ) !== false ) {

			$item_name = explode( ":", trim( $item ), 2 );
			$item_name = str_replace( "+=+", ":", $item_name[0] );

			if ( array_key_exists( $item_name, $items_to_search_for ) ) {

				$items_to_search_for[$item_name]['belongs_to'] = "ok";

			}

			array_push($items_already_categorized, $item_name);

		}

		fclose($myfile_categorization);

	}

	/**********************************************************************************************************************
	*
	* We proceed to iteration through all files, items....
	*
	***********************************************************************************************************************/

	// Count total items in memory
	$total_items_in_memory 	= count($items_to_search_for);

	// Prepare an array containing all items we will iterate through
	$myfile_to_categorize = fopen($path_file_to_categorize, "r");
	$to_categorize_array = array();
	while(($item = fgets($myfile_to_categorize)) !== false){
		array_push($to_categorize_array, trim($item));
	}
	fclose($myfile_to_categorize);

	// Prepare an array containing all files we will iterate through
	$all_files_paths = fopen($path_file_all_php_files, "r");
	$all_files_array = array();
	while(($file_path = fgets($all_files_paths)) !== false){
		array_push($all_files_array, trim($file_path));
	}
	fclose($all_files_paths);

	// Get the number of items processed until now
	$processed_items = count($items_already_categorized);

	// Open the file in which we will save searching results as and when
	$myfile_categorization = fopen($path_file_categorization, "a");

	// We save start time to save progressing data into DB each 2 secs
	$start_time = time();

	/***********************************************************************************************************************
	* Iteration 1: Search in all files for exact match for all items
	***********************************************************************************************************************/
	if($aDBc_iteration == 1){

		$file_line_index = 1;

		foreach($all_files_array as $file_path){
			// We write the progress for ajax. We write each 2 sec to load fast. Then we save current status to DB to load it in case of timeout
			if(time() - $start_time >= 2){

				// Test if the user wants to stop the scan
				if (aDBc_stop_scan($items_type)) {
					wp_die();
				}

				// Save progress in database
				$progress_data = $aDBc_file_line . ":" . $aDBc_total_files;

				update_option("aDBc_temp_progress_scan_" . $items_type, $progress_data, "no");
				update_option("aDBc_temp_last_item_line_" . $items_type, $aDBc_item_line, "no");
				update_option("aDBc_temp_last_file_line_" . $items_type, $aDBc_file_line, "no");

				$start_time = time();

			}

			// Skip until we found the last file before timeout
			if($file_line_index < $aDBc_file_line){
				$file_line_index++;
				continue;
			}

			$aDBc_file_content = file_get_contents($file_path);

			$item_line_index = 1;

			foreach($to_categorize_array as $item_name){
				// Skip until we found the last item before timeout
				if($item_line_index < $aDBc_item_line){
					$item_line_index++;
					continue;
				}
				// Before scaning the item, we test if the item has not been already categorized
				if(array_key_exists($item_name, $items_to_search_for) && $items_to_search_for[$item_name]['belongs_to'] != "ok"){
					// If exact match found
					if(strpos($aDBc_file_content, $item_name) !== false){
						// We update data, identify plugin or theme names,....
						$owner_name_type = aDBc_get_owner_name_from_path($item_name, $file_path);
						fwrite($myfile_categorization, str_replace(":", "+=+", $item_name) . ":" . $owner_name_type[0] . ":" . $owner_name_type[1] . "\n");
						$processed_items++;
						// Put ok in belongs_to
						$items_to_search_for[$item_name]['belongs_to'] = "ok";
						// If we have categorized all items, break from all loops (2 loops)
						if($processed_items >= $total_items_in_memory){
							break 2;
						}
					}
				}

				$aDBc_item_line++;
				$item_line_index++;

			}

			$aDBc_item_line = 1;

			$file_line_index++;
			$aDBc_file_line++;

		}

		// If we have not categorized all items in iteration 1, we should execute iteration 2
		if($processed_items < $total_items_in_memory){
			$aDBc_iteration = 2;
			$aDBc_file_line = 1;
			$aDBc_item_line = 1;
		}
	}

	/***********************************************************************************************************************
	* Iteration 2: Search in all files for partial match for items that are not categorized in iteration 1
	***********************************************************************************************************************/
	if($aDBc_iteration == 2){
		// If we are in iteration 2, we start by verifying if maybe_scores option exists, if so, load its data to $items_to_search_for
		$maybe_scores_option = get_option("aDBc_temp_maybe_scores_" . $items_type);
		if(!empty($maybe_scores_option)){

			$maybe_array = json_decode($maybe_scores_option, true);
			foreach($maybe_array as $item){
				$info = explode(":", trim($item), 2);
				$name = str_replace("+=+", ":", $info[0]);
				if(array_key_exists($name, $items_to_search_for)){
					$items_to_search_for[$name]['maybe_belongs_to'] = $info[1];
				}
			}
			// Once we finish, we delete this option
			delete_option("aDBc_temp_maybe_scores_" . $items_type);

		}

		$file_line_index = 1;
		$half_files = $aDBc_total_files / 2;
		foreach($all_files_array as $file_path){
			// We write the progress for ajax. We write each 2 sec to load fast. Then we save current status to DB to load it in case of timeout
			if(time() - $start_time >= 2){

				// Test if the user wants to stop the scan
				if (aDBc_stop_scan($items_type)) {
					wp_die();
				}

				$progress_data = ($half_files + $aDBc_file_line) . ":" . $aDBc_total_files;

				update_option("aDBc_temp_progress_scan_" . $items_type, $progress_data, "no");
				update_option("aDBc_temp_last_item_line_" . $items_type, $aDBc_item_line, "no");
				update_option("aDBc_temp_last_file_line_" . $items_type, $aDBc_file_line, "no");

				// Update maybe scores in DB since we are in iteration 2
				$maybe_array = array();
				foreach($items_to_search_for as $aDBc_item => $aDBc_info){
					if($aDBc_info['belongs_to'] != "ok" && !empty($aDBc_info['maybe_belongs_to'])){
						array_push($maybe_array, str_replace(":", "+=+", $aDBc_item) . ":" . $aDBc_info['maybe_belongs_to']);
					}
				}
				if(!empty($maybe_array)){
					// xxx saving a huge amount of data in the database may cause DB size issue when timeout occurs!
					update_option("aDBc_temp_maybe_scores_" . $items_type, json_encode($maybe_array), "no");
				}

				$start_time = time();

			}

			// Skip until we found the last file before timeout
			if($file_line_index < $aDBc_file_line){
				$file_line_index++;
				continue;
			}
			$aDBc_file_content = strtolower(file_get_contents($file_path));
			$item_line_index = 1;
			foreach($to_categorize_array as $item_name){
				// Skip until we found the last item before timeout
				if($item_line_index < $aDBc_item_line){
					$item_line_index++;
					continue;
				}

				// Before scaning the item, we test if the item has not been already categorized
				if(array_key_exists($item_name, $items_to_search_for) && $items_to_search_for[$item_name]['belongs_to'] != "ok"){
					// Find partial match. If found, add it directly to maybe_belongs_to in $items_to_search_for
					aDBc_search_for_partial_match($item_name, $aDBc_file_content, $file_path, $items_to_search_for);
				}

				$aDBc_item_line++;
				$item_line_index++;
			}
			$aDBc_item_line = 1;
			$file_line_index++;
			$aDBc_file_line++;
		}

		// After finishing all partial matches. Write results to file
		foreach($items_to_search_for as $aDBc_item => $aDBc_info){

			if($aDBc_info['belongs_to'] != "ok"){

				$aDBc_maybe_belongs_to_parts = explode("/", $aDBc_info['maybe_belongs_to']);

				// If the part1 is not empty, we will use it, else use the part 2
				if(!empty($aDBc_maybe_belongs_to_parts[0])){

					$aDBc_maybe_belongs_to_info = explode("|", $aDBc_maybe_belongs_to_parts[0]);
					$belongs_to = $aDBc_maybe_belongs_to_info[0] == "w" ? "" : $aDBc_maybe_belongs_to_info[0];
					// If $aDBc_maybe_belongs_to_info[2] equals to 100%, then delete pourcentage
					if($aDBc_maybe_belongs_to_info[2] != "100"){
						$belongs_to .= " (".$aDBc_maybe_belongs_to_info[2]."%)";
					}
					$type = $aDBc_maybe_belongs_to_info[1];

				}else if(!empty($aDBc_maybe_belongs_to_parts[1])){

					$aDBc_maybe_belongs_to_info = explode("|", $aDBc_maybe_belongs_to_parts[1]);
					$belongs_to = $aDBc_maybe_belongs_to_info[0] == "w" ? "" : $aDBc_maybe_belongs_to_info[0];
					// If $aDBc_maybe_belongs_to_info[2] equals to 100%, then delete pourcentage
					if($aDBc_maybe_belongs_to_info[2] != "100"){
						$belongs_to .= " (".$aDBc_maybe_belongs_to_info[2]."%)";
					}
					$type = $aDBc_maybe_belongs_to_info[1];

				}else{

					// As final step, make all items to orphan if they have an empty "belong_to"
					$belongs_to = "o";
					$type = "o";
				}

				$aDBc_items_status = str_replace(":", "+=+", $aDBc_item) . ":" . $belongs_to . ":" . $type;
				fwrite($myfile_categorization, $aDBc_items_status . "\n");

			}
		}
	}

	fclose($myfile_categorization);

	// After the search has been finished, close files and delete the all temp options that have been added to DB
	// First, we process the results file to delete any duplicated entries caused by scanning selected items that are already scanned
	// I have added this tests to prevent the case in which the results file is deleted then the page refresh, we will loose data!
	$path_temp_results = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_temp.txt";
	if(file_exists($path_file_categorization)){
		$unique_items = array();
		$total_duplicated = 0;
		// Get all categorized items and add them to any array. New ones will overwide old ones to keep the newest scan results
		$results_file = fopen($path_file_categorization, "r");
		while(($item = fgets($results_file)) !== false){
			$columns = explode(":", trim($item), 2);
			if(!empty($unique_items[$columns[0]])){
				$total_duplicated++;
			}
			$unique_items[$columns[0]] = $columns[1];
		}
		fclose($results_file);

		// If duplicated found, proceed, otherwise do nothing
		if($total_duplicated > 0){
			// We start be deleting the temp file to prevent apend results in it
			if(file_exists($path_temp_results))
				unlink($path_temp_results);
			// Write results to a temp file
			$temp_file = fopen($path_temp_results, "a");
			foreach($unique_items as $item_name => $scan){
				fwrite($temp_file, $item_name . ":" . $scan . "\n");
			}
			fclose($temp_file);

			// Delete old results file and rename new one
			unlink($path_file_categorization);
			rename($path_temp_results, $path_file_categorization);
		}
	}else{
		// If the results files does not exists, test if temp one exists and rename it
		if(file_exists($path_temp_results))
			rename($path_temp_results, $path_file_categorization);
	}

	// Delete temp options and files
	aDBc_delete_temp_options_files_of_scan($items_type);

	// Let know shutdown function to not load (because the shutdown function loads always after a script has finished, even without timeout error)
	$aDBc_search_has_finished = "yes";

	// Always die in functions echoing ajax content
	wp_die();
}

/************************************************************************************
 * This function stops a running scan/search for a specific item type if it exists
 * **********************************************************************************/
function aDBc_stop_scan($items_type) {
    if(file_exists(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/stop_scan" . $items_type . ".txt")){
        aDBc_delete_temp_options_files_of_scan($items_type);
		global $aDBc_search_has_finished;
        $aDBc_search_has_finished = "yes"; // This variable should be managed properly.
        return true; // Indicate that the scan should stop.
    }
    return false; // Indicate that the scan should not stop.
}

/************************************************************************************************
* This fuction stops a running scan/search
************************************************************************************************/
function aDBc_stop_search(){

	// We create a temp file so that the function of scan knows that we want to stop the scan

    // The $_REQUEST contains all the data sent via ajax
    if(isset($_REQUEST)){

		// Get item_type
		$items_type = $_REQUEST['aDBc_item_type'];

		fopen(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/stop_scan" . $items_type . ".txt", "a");

		// xxx, close the file?

		// Always die in functions echoing ajax content
		wp_die();
	}
}

/**************************************************************************
* This function deletes all temps options and files of a scan process
***************************************************************************/
function aDBc_delete_temp_options_files_of_scan($items_type){

	// Delete temp files
	if(file_exists(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths_{$items_type}.txt"))
		    unlink(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths_{$items_type}.txt");

	if(file_exists(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_to_categorize.txt"))
	        unlink(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_to_categorize.txt");

	// Delete aDBc_stop_scan file in case it was created after the two itereations in the scan function. In this case that file will not have any effect and should be deleted at the end of the scan function
	if(file_exists(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/stop_scan" . $items_type . ".txt"))
		    unlink(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/stop_scan" . $items_type . ".txt");

	delete_option("aDBc_temp_last_item_line_" 				. $items_type);
	delete_option("aDBc_temp_last_file_line_" 				. $items_type);
	delete_option("aDBc_temp_last_iteration_" 				. $items_type);
	delete_option("aDBc_temp_total_files_" 					. $items_type);
	delete_option("aDBc_temp_maybe_scores_" 				. $items_type);
	delete_option("aDBc_temp_currently_scanning_" 			. $items_type);
	delete_option("aDBc_temp_progress_scan_" 				. $items_type);
	delete_option("aDBc_temp_progress_files_preparation_" 	. $items_type);
	delete_option("aDBc_temp_last_collected_file_path_" 	. $items_type);
	delete_option("aDBc_temp_items_to_scan_" 				. $items_type);
	delete_option("aDBc_temp_scan_type_" 					. $items_type);
	delete_option("aDBc_temp_current_scan_step_" 			. $items_type);

}

/**************************************************************************
* This function is executed if timeout is reached during a scan process
* to save data in DB to reload it later
***************************************************************************/
function aDBc_shutdown_due_to_timeout(){

	global $aDBc_search_has_finished;

	if($aDBc_search_has_finished == "no"){

		// Stores the item type we are dealing with: tables, options or tasks
		global $item_type_for_shutdown;

		// Stores the iteration number: either 0, 1 or 2
		global $aDBc_iteration;

		// Delete temp option that prevents other scans to run
		delete_option("aDBc_temp_currently_scanning_" . $item_type_for_shutdown);

		// if the scan has started save the last iteration number either 0, 1 or 2
		update_option("aDBc_temp_last_iteration_" . $item_type_for_shutdown, $aDBc_iteration, "no");

		// if the scan is in the file preparation step (iteration 0)
		if ($aDBc_iteration == 0){

			// Stores the last file that have been collected
			global $last_collected_file_path_for_shutdown;
			update_option("aDBc_temp_last_collected_file_path_{$item_type_for_shutdown}", $last_collected_file_path_for_shutdown, "no");

			// Save the progress of files preparation
			global $aDBc_number_collected_files;
			global $aDBc_number_scanned_files;
			$progress_data = $aDBc_number_collected_files . ":" . $aDBc_number_scanned_files;
			update_option("aDBc_temp_progress_files_preparation_" . $item_type_for_shutdown, $progress_data, "no");
			
			// Save the items to scan & scan type
			global $aDBc_items_to_scan;
			update_option("aDBc_temp_items_to_scan_" . $item_type_for_shutdown, $aDBc_items_to_scan, "no");

			global $aDBc_scan_type;
			update_option("aDBc_temp_scan_type_" . $item_type_for_shutdown, $aDBc_scan_type, "no");

		}

		// If the scan is in the process of relation detection (iteration 1 or 2)
		if ($aDBc_iteration == 1 || $aDBc_iteration == 2){

			// Stores the last line that have been processed
			global $aDBc_item_line;
			// Stores the last line that have been reached
			global $aDBc_file_line;

			update_option("aDBc_temp_last_item_line_" . $item_type_for_shutdown, $aDBc_item_line, "no");
			update_option("aDBc_temp_last_file_line_" . $item_type_for_shutdown, $aDBc_file_line, "no");

		}

		// If we are in iteration 2, we save maybe scores to DB to reload them later
		if($aDBc_iteration == 2){

			// Get array containing scan results
			global $items_to_search_for;

			// Get maybe scores
			$maybe_array = array();
			foreach($items_to_search_for as $aDBc_item => $aDBc_info){
				if($aDBc_info['belongs_to'] != "ok" && !empty($aDBc_info['maybe_belongs_to'])){
					array_push($maybe_array, str_replace(":", "+=+", $aDBc_item) . ":" . $aDBc_info['maybe_belongs_to']);
				}
			}

			// Save temp maybe scores in DB
			// xxx saving a huge amount of data in the database may cause DB size issue when timeout occurs!
			if(empty($maybe_array)){
				update_option("aDBc_temp_maybe_scores_" . $item_type_for_shutdown, "", "no");
			}else{
				update_option("aDBc_temp_maybe_scores_" . $item_type_for_shutdown, json_encode($maybe_array), "no");
			}
		}

	}
}

/************************************************************************************************
* This fuction tries to find any partial match of the item_name in the current file then returns:
************************************************************************************************/
function aDBc_search_for_partial_match($aDBc_item_name, $aDBc_file_content, $file_path, &$items_to_search_for){

	// call the last best maybe score
	$aDBc_maybe_score = empty($items_to_search_for[$aDBc_item_name]['maybe_belongs_to']) ? "/" : $items_to_search_for[$aDBc_item_name]['maybe_belongs_to'];

	$aDBc_maybe_belongs_to_parts = explode("/", $aDBc_maybe_score);

	// In itereation 2, change name to lowercase
	$item_name = strtolower($aDBc_item_name);

	$aDBc_item_name_len = strlen($item_name);
	$aDBc_is_new_score_found = 0;

	$aDBc_percent1 = 35;
	$aDBc_item_part1 = substr($item_name, 0, (int)(($aDBc_percent1 * $aDBc_item_name_len) / 100));
	$aDBc_percent2 = 75;
	$aDBc_item_part2 = substr($item_name, (int)(-(($aDBc_percent2 * $aDBc_item_name_len) / 100)));

	// If aDBc_item_part1 appears in the file content
	if(strpos($aDBc_file_content, $aDBc_item_part1) !== false){

		$aDBc_maybe_belongs_to_info_part1 = explode("|", $aDBc_maybe_belongs_to_parts[0]);
		$aDBc_maybe_best_score_found = empty($aDBc_maybe_belongs_to_info_part1[2]) ? $aDBc_percent1 : $aDBc_maybe_belongs_to_info_part1[2];
		// Search for all combinations starting from the beginning of the item name
		for ($i = $aDBc_item_name_len; $i > 1; $i--) {
			$aDBc_substring = substr($item_name, 0, $i);
			$aDBc_percent = (strlen($aDBc_substring) * 100) / $aDBc_item_name_len;
			if($aDBc_percent > $aDBc_maybe_best_score_found){
				if(strpos($aDBc_file_content, $aDBc_substring) !== false){
					// Bingo, we have find a percent %
					$aDBc_maybe_best_score_found = round($aDBc_percent, 2);
					$aDBc_is_new_score_found = 1;
					// Break after the first item found, since it is the longest
					break;
				}
			}else{
				break;
			}
		}

	}

	// If aDBc_item_part2 appears in the file content
	if(strpos($aDBc_file_content, $aDBc_item_part2) !== false){

		$aDBc_maybe_belongs_to_info_part2 = explode("|", $aDBc_maybe_belongs_to_parts[1]);
		$aDBc_maybe_best_score_found = empty($aDBc_maybe_belongs_to_info_part2[2]) ? $aDBc_percent2 : $aDBc_maybe_belongs_to_info_part2[2];
		// Search for all combinations starting from the end of the item name
		for ($i = 0; $i < $aDBc_item_name_len; $i++) {
			$aDBc_substring = substr($item_name, $i);
			$aDBc_percent = (strlen($aDBc_substring) * 100) / $aDBc_item_name_len;
			if($aDBc_percent > $aDBc_maybe_best_score_found){
				if(strpos($aDBc_file_content, $aDBc_substring) !== false){
					// Bingo, we have find a percent %
					$aDBc_maybe_best_score_found = round($aDBc_percent, 2);
					$aDBc_is_new_score_found = 2;
					// Break after the first item found, since it is the longest
					break;
				}
			}else{
				break;
			}
		}

	}

	// Test is new score was found in order to update data
	if($aDBc_is_new_score_found){
		$aDBc_type_detected = 0;
		// Is a plugin?
		if(strpos($file_path, ADBC_WP_PLUGINS_DIR_PATH) !== false){
			$aDBc_path = str_replace(ADBC_WP_PLUGINS_DIR_PATH."/", "", $file_path);
			$plugin_name = explode("/", $aDBc_path, 2);
			// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
			$aDBc_new_part = $plugin_name[0] . "|p|" . $aDBc_maybe_best_score_found;
			if($aDBc_is_new_score_found == "1"){
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
			}else{
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
			}
			$aDBc_type_detected = 1;
		}
		// If not a plugin, then is a theme?
		if(!$aDBc_type_detected){

			// Prepare WP Themes directories paths (useful to detect if an item belongs to a theme and detect the theme name)
			global $wp_theme_directories;
			$aDBc_themes_paths_array = array();
			foreach($wp_theme_directories as $aDBc_theme_path){
				array_push($aDBc_themes_paths_array, str_replace('\\' ,'/', $aDBc_theme_path));
			}

			foreach($aDBc_themes_paths_array as $aDBc_theme_path){
				if(strpos($file_path, $aDBc_theme_path) !== false){
					$aDBc_path = str_replace($aDBc_theme_path."/", "", $file_path);
					$theme_name = explode("/", $aDBc_path, 2);
					// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
					$aDBc_new_part = $theme_name[0] . "|t|" . $aDBc_maybe_best_score_found;
					if($aDBc_is_new_score_found == "1"){
						$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
					}else{
						$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
					}
					$aDBc_type_detected = 1;
					break;
				}
			}
		}
		// xxx If not a plugin and not a theme, then affect it to WP?
		if(!$aDBc_type_detected){
			// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
			$aDBc_new_part = "w|w|" . $aDBc_maybe_best_score_found;
			if($aDBc_is_new_score_found == "1"){
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
			}else{
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
			}
		}
	}
}

/**************************************************************************************************************
* Return an array containing the name and the type of the owner of the item in parameter based on the file path
**************************************************************************************************************/
function aDBc_get_owner_name_from_path($item_name, $full_path){

	$owner_name_type = array();

	// Is a plugin?
	if(strpos($full_path, ADBC_WP_PLUGINS_DIR_PATH) !== false){
		$aDBc_path = str_replace(ADBC_WP_PLUGINS_DIR_PATH."/", "", $full_path);
		$plugin_name = explode("/", $aDBc_path, 2);
		$owner_name_type[0] = $plugin_name[0];
		$owner_name_type[1] = "p";
		return $owner_name_type;
	}

	// If not a plugin, then is a theme?
	// Prepare WP Themes directories paths (useful to detect if an item belongs to a theme and detect the theme name)
	global $wp_theme_directories;
	$aDBc_themes_paths_array = array();
	foreach($wp_theme_directories as $aDBc_theme_path){
		array_push($aDBc_themes_paths_array, str_replace('\\' ,'/', $aDBc_theme_path));
	}

	foreach($aDBc_themes_paths_array as $aDBc_theme_path){
		if(strpos($full_path, $aDBc_theme_path) !== false){
			$aDBc_path = str_replace($aDBc_theme_path."/", "", $full_path);
			$theme_name = explode("/", $aDBc_path, 2);
			$owner_name_type[0] = $theme_name[0];
			$owner_name_type[1] = "t";
			return $owner_name_type;
		}
	}

	// If not a plugin and not a theme, then affect it to WP? Maybe later I should return the file name instead of affect it to WP
	$owner_name_type[0] = "w";
	$owner_name_type[1] = "w";
	return $owner_name_type;
}

/**************************************************************************************************************
 * Create an array containing all folders to scan
 * ************************************************************************************************************/
function aDBc_get_folders_to_scan(){
	$folders_to_scan = array();

	// Search in plugins directory
	if(is_dir(ADBC_WP_PLUGINS_DIR_PATH)){
		array_push($folders_to_scan, ADBC_WP_PLUGINS_DIR_PATH);
	}

	// Search in MU must use plugins
	if(is_dir(ADBC_WPMU_PLUGIN_DIR_PATH)){
		array_push($folders_to_scan, ADBC_WPMU_PLUGIN_DIR_PATH);
	}

	// Search in themes directories
	global $wp_theme_directories;
	foreach($wp_theme_directories as $aDBc_theme_path){
		$path = str_replace('\\' ,'/', $aDBc_theme_path);
		if(is_dir($path)){
			array_push($folders_to_scan, $path);
		}
	}

	return $folders_to_scan;
}

/**************************************************************************************************************
 * Returns an array of all existing dropins files paths
 * ************************************************************************************************************/
function aDBc_get_dropins_files_paths(){

	// List of wp default dropins files names should be updated if new dropins are added to wordpress
	$wp_default_dropins = array(
		'advanced-cache.php',
		'db.php',
		'maintenance.php',
		'object-cache.php',
		'sunrise.php',
		'db-error.php',
		'install.php',
		'php-error.php',
		'fatal-error-handler.php',
		'blog-deleted.php',
		'blog-inactive.php',
		'blog-suspended.php'
	);

	// Collect all existing dropins files paths
	$dropins_files_paths = array();

	foreach($wp_default_dropins as $dropin_file){
		$dropin_file_path = ADBC_WP_CONTENT_DIR_PATH . "/" . $dropin_file;
		if(file_exists($dropin_file_path)){
			array_push($dropins_files_paths, $dropin_file_path);
		}
	}

    return $dropins_files_paths;

}


/******************************************************************
* Create list of all php files in the wordpress installation
*******************************************************************/
function aDBc_refresh_and_create_php_files_paths($items_type){

	// Stores the number of collected files and total scanned files for shutdown function and progress bar
	$progress_files_preparation = get_option("aDBc_temp_progress_files_preparation_" . $items_type, "0:0");
	$progress_files_preparation = explode(":", $progress_files_preparation);

	global $aDBc_number_collected_files;
	$aDBc_number_collected_files = $progress_files_preparation[0];
	
	global $aDBc_number_scanned_files;
	$aDBc_number_scanned_files = $progress_files_preparation[1];

	// Get the last collected file path if exists
	$last_collected_file_path = get_option("aDBc_temp_last_collected_file_path_{$items_type}", "");

	$all_paths_file_name = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths_{$items_type}.txt";

	// if we are starting a new scan, delete the file containing all php files paths
	if(empty($last_collected_file_path) && file_exists($all_paths_file_name)){
		@unlink($all_paths_file_name);
	}

	// First we collect all existing dropins files paths if we are starting a new scan
	if(empty($last_collected_file_path)){
		$dropins_files_paths = aDBc_get_dropins_files_paths();
		if(!empty($dropins_files_paths)){
			// Save dropins files paths in the all_files_paths file
			$myfile = fopen($all_paths_file_name, "a");
			foreach($dropins_files_paths as $dropin_file_path){
				fwrite($myfile, $dropin_file_path . "\n");
			}
			fclose($myfile);
		}
	}
	
	// Get all other folders to scan
	$folders_to_scan = aDBc_get_folders_to_scan();

	// If the last collected file path is empty then we are starting a new scan, so we scan all folders
	$found_folder_to_resume_from = empty($last_collected_file_path) ? true : false;

	foreach($folders_to_scan as $folder_to_scan){

		// If we still haven't found the folder to resume from, then skip this folder
		if ($found_folder_to_resume_from === false && strpos($last_collected_file_path, $folder_to_scan) === false) {
			continue;
		}

		// If we found the folder to resume from, then set the flag to true
		$found_folder_to_resume_from = true;


		// Scan the folder for php files and save them in the all_files_paths file
		aDBc_create_php_files_urls($folder_to_scan, $all_paths_file_name, $last_collected_file_path);

	}

	// save progress in database when the file preparation is finished to save the last progress data
	$progress_files_preparation = "{$aDBc_number_collected_files}:{$aDBc_number_scanned_files}";
	update_option("aDBc_temp_progress_files_preparation_" . $items_type, $progress_files_preparation, "no");

}

/******************************************************************
 * Create list of all php files starting from the path in parameter
 * $folder_to_scan : folder to search in for php files
 * $output_file : the file where to save files paths
 * $resume_from_file_path : the file path to resume the search from
 * ****************************************************************/
function aDBc_create_php_files_urls($folder_to_scan, $output_file, $resume_from_file_path = '') {

    // Convert any backslashes to forward slashes in paths for consistency
    $folder_to_scan = str_replace('\\', '/', $folder_to_scan);
    $resume_from_file_path = str_replace('\\', '/', $resume_from_file_path);

    // Flag to indicate if the resume file has been found; default to true if no resume path provided
    $found_resume_from_file_flag = $resume_from_file_path === '' ? true : false;

    // Determine the starting folder for scanning - resume file's folder or the specified folder
    $resume_file_folder = $resume_from_file_path !== '' ? dirname($resume_from_file_path) : $folder_to_scan;
    $resume_from_file_name = basename($resume_from_file_path);

    // Start scanning for PHP files in the determined starting folder
    aDBc_collect_php_files_from_folder($resume_file_folder, $output_file, $resume_from_file_name, $found_resume_from_file_flag);

    // If a specific file is set to resume from, process its parent directories as well
    if ($resume_from_file_path !== '') {
        $current_dir = $resume_file_folder;
        // Loop through parent directories until reaching the top-level folder to scan
        while ($current_dir && realpath($current_dir) !== realpath($folder_to_scan)) {
            $parent_dir = dirname($current_dir);

            // Break the loop if the parent directory is the same as the current one to avoid an infinite loop
            if ($parent_dir === $current_dir) {
                break;
            }

            // Move to the parent directory for the next iteration
            $current_dir = $parent_dir;

            // Call the function to scan the parent directory, updating the last processed directory
            aDBc_collect_php_files_from_folder($current_dir, $output_file, '', $found_resume_from_file_flag, basename($resume_file_folder));

            // Update the last processed directory
            $resume_file_folder = $current_dir;
        }
    }
}

/******************************************************************
 * Collect php files from a folder used by aDBc_create_php_files_urls()
 * $directory : folder to search in for php files
 * $output_file : the file where to save php files paths
 * $resume_file : the file path to resume the search from
 * $found_resume_file : a flag to indicate if the resume file has been found
 * $last_processed_dir : the last processed directory to start processing files after it
 * ****************************************************************/
function aDBc_collect_php_files_from_folder($directory, $output_file, $resume_file = '', &$found_resume_file = false, $last_processed_dir = '') {

    global $last_collected_file_path_for_shutdown, $item_type_for_shutdown, $aDBc_number_collected_files, $aDBc_number_scanned_files;

    $directory = rtrim(str_replace('\\', '/', $directory), '/'); // Normalize and trim trailing slash
    $entries = scandir($directory); // Get directory contents
    natsort($entries); // Sort entries naturally

    $should_start_processing = $last_processed_dir === '' ? true : false; // Flag to determine when to start processing

    $start_time = time(); // Start time for progress update

    foreach ($entries as $entry) {

        if ($entry === '.' || $entry === '..') { // Skip current and parent directory entries
            continue;
        }

        $full_path = $directory . '/' . $entry; // Construct full path without realpath

        // Update progress every 2 seconds
        if (time() - $start_time >= 2) {
            if (aDBc_stop_scan($item_type_for_shutdown)) { // Check if scan should be stopped
                wp_die();
            }

            // Save progress in database
            $progress_status = "{$aDBc_number_collected_files}:{$aDBc_number_scanned_files}";
            update_option("aDBc_temp_progress_files_preparation_" . $item_type_for_shutdown, $progress_status, "no");
            $start_time = time();
        }

        if (!$should_start_processing && $entry === $last_processed_dir) {
            $should_start_processing = true;
            continue;
        }

        if (!$found_resume_file && $resume_file !== '' && $entry === basename($resume_file)) {
            $found_resume_file = true;
            continue;
        }

        if ($should_start_processing && ($found_resume_file || $resume_file === '')) {
            if (is_file($full_path)) {
				if(pathinfo($full_path, PATHINFO_EXTENSION) === 'php'){
					file_put_contents($output_file, $full_path . PHP_EOL, FILE_APPEND);
					$aDBc_number_collected_files++;	
				}
                $last_collected_file_path_for_shutdown = $full_path;
            } elseif (is_dir($full_path)) {
                aDBc_collect_php_files_from_folder($full_path, $output_file, $resume_file, $found_resume_file);
            }
            $aDBc_number_scanned_files++; // Increment scanned files count here for PHP files and directories
        }
    }
}

/*************************************************************************************************************
* This functions refreshes the categorization file after delete process to keep only valid entries in the file
*************************************************************************************************************/
function aDBc_refresh_categorization_file_after_delete($names_deleted, $items_type){

	// Get the file path
	$path_file_categorization = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . ".txt";

	// Test if there are any items that have been deleted to prevent wasting time && moreover the file exists
	if(count($names_deleted) > 0 && file_exists($path_file_categorization)){

		$file_categorization = fopen($path_file_categorization, "r");

		// Prepare an array containing new file info
		$array_new_file = array();

		// Count total lines in file
		$total_lines = 0;

		while(($item = fgets($file_categorization)) !== false){
			$total_lines++;
			$item_name = explode(":", trim($item), 2);
			$item_name = str_replace("+=+", ":", $item_name[0]);
			if(!in_array($item_name, $names_deleted)){
				array_push($array_new_file, trim($item));
			}
		}
		fclose($file_categorization);

		// We will refresh the file only if the number of new lines is lover than number of old files. To prevent refreshing the file when deleting items not existing in file
		if(count($array_new_file) < $total_lines){
			// Delete old file
			@unlink($path_file_categorization);

			// Create a new file which will hold new info
			$file_categorization = fopen($path_file_categorization, "a");

			foreach($array_new_file as $aDBc_item){
				fwrite($file_categorization, $aDBc_item . "\n");
			}
			fclose($file_categorization);
		}
	}
}

/*************************************************************************************************************
* This functions retrieves the list of plugins folders names
*************************************************************************************************************/
function aDBc_get_plugins_folder_names(){
	// Get all plugins info
	$all_plugins = get_plugins();
	// Prepare an array that will contain plugins folders names
	$plugins_folders = array();
	foreach(array_keys($all_plugins) as $plugin_file){
		$plugin_data = explode("/", $plugin_file);
		array_push($plugins_folders, $plugin_data[0]);
	}
	return $plugins_folders;
}

/*************************************************************************************************************
* This functions retrieves the list of themes folders names
*************************************************************************************************************/
function aDBc_get_themes_folder_names(){
	// Get all themes info
	$all_themes = wp_get_themes();
	// Prepare an array that will contain themes folders names
	$themes_folders = array();
	foreach(array_keys($all_themes) as $theme_file){
		$theme_data = explode("/", $theme_file);
		array_push($themes_folders, $theme_data[0]);
	}
	return $themes_folders;
}

/*************************************************************************************************************
* Prepares a list of plugins/themes to which an orphaned item may belong after double check
*************************************************************************************************************/
function aDBc_get_correction_info_for_orphaned_items($json_info){

	$info_array = json_decode($json_info, true);

	// If array contains 0 elements, return empty string (this situation should not happen, just to be sure...)
	// xxx test if $info_array is countable
	if(count($info_array) == 0)
		return "";

	$toolip = "<span class='aDBc-tooltips-headers'>
				<img class='aDBc-info-image' src='".  ADBC_PLUGIN_DIR_PATH . '/images/information_orange.svg' . "'/>
				<span>";

	if(count($info_array) == 1){
		$toolip .= __('Belongs to:','advanced-database-cleaner');
	}else if(count($info_array) > 1){
		$toolip .= __('Belongs to one of these:','advanced-database-cleaner');
	}

	foreach($info_array as $info){
		$columns = explode(":", $info);
		$toolip .= "<div style='background:#fff;color:#000;border-radius:1px;padding:3px;margin:2px'>";
		$toolip .= $columns[0];
		$toolip .= " (";
		$toolip .= $columns[1] == "p" ? "<font color='#00BAFF'>" . __('plugin','advanced-database-cleaner') . "</font>" : "<font color='#45C966'>" . __('theme','advanced-database-cleaner') . "</font>";
		$toolip .= " - <font color='red'>" . __('Not installed','advanced-database-cleaner') . "</font>)";
		$toolip .= "</div>";
	}
	$toolip .= "</span></span>";

	return $toolip;
}

/*************************************************************************************************************
* Edits the categorization of selected items
*************************************************************************************************************/
function aDBc_edit_categorization_of_items($items_type, $new_belongs_to, $send_data_to_server_or_not){

	// Open the file in which the items to edit have been saved
	$path_items_manually = @fopen(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_manually_correction_temp.txt", "r");
	if($path_items_manually){

		// Get the new belongs_to info made by the user
		$columns = explode("|", trim($new_belongs_to));
		$belongs_to = sanitize_html_class($columns[0]) . ":" . sanitize_html_class($columns[1]);

		// Prepare an array containing new categorizations
		$items_correction_array = array();
		while(($item = fgets($path_items_manually)) !== false) {
			$item = trim($item);
			if(!empty($item)){
				$items_correction_array[$item] = $belongs_to;
			}
		}

		/* First, we verify if there are items in the $items_correction_array but do not exist in the scan official categorization file
		In this case, we add missing items first to that file. Otherwise, manual corrections will not be loaded for missing items and they will still be marked as "uncategorized". Because the plugin loads categorizations only for scanned items */
		$path_to_scan_file = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . ".txt";
		// Prepare an array containing all scanned items names
		$scanned_items_array = array();
		if(file_exists($path_to_scan_file)){
			$scan_file = fopen($path_to_scan_file, "r");
			while(($item = fgets($scan_file)) !== false) {
				$item = trim($item);
				if(!empty($item)){
					$columns = explode(":", $item, 2);
					// We replace +=+ by :
					$item_name = str_replace("+=+", ":", $columns[0]);
					array_push($scanned_items_array, $item_name);
				}
			}
			fclose($scan_file);
		}
		// Append missing items to the scan file. In case it does not exist, create it.
		$scan_file = fopen($path_to_scan_file, "a");
		foreach($items_correction_array as $item => $belongs_to){
			// Test if this item exists in the scan file or should we add it
			if(!in_array($item, $scanned_items_array)){
				fwrite($scan_file, str_replace(":", "+=+", $item) . ":" . $belongs_to . "\n");
			}
		}
		fclose($scan_file);

		// Get the old categorization made by the user
		$old_corrections_path = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type ."_corrected_manually.txt";
		if(file_exists($old_corrections_path)){
			$old_corrections_array = json_decode(trim(file_get_contents($old_corrections_path)), true);
			// Loop over the new categorization and add/edit corresponding ones in old file
			foreach($items_correction_array as $item => $belongs_to){
				// If the new categorization exist, it will be overwide. Otherwise, it will be added to the array
				$old_corrections_array[$item] = $belongs_to;
			}
			// Save the array containing old and new categorizations
			$new_file = @fopen($old_corrections_path, "w");
			if($new_file){
				fwrite($new_file, json_encode($old_corrections_array));
				fclose($new_file);
			}

		}else{
			// If old categorization does not exist, then just save the new ones
			$new_file = @fopen($old_corrections_path, "w");
			if($new_file){
				fwrite($new_file, json_encode($items_correction_array));
				fclose($new_file);
			}
		}

		// xxx Test if user has checked the checkbox to send correction to server
		/*if($send_data_to_server_or_not == 1){
			// maybe not for now. Just hide checkbox
		}*/

		fclose($path_items_manually);
		return __("Modifications saved successfully!", "advanced-database-cleaner");
	}
	return "";
}

/*************************************************************************************************************
 * This function returns an array of all adbc temp options
 * ***********************************************************************************************************/
function aDBc_get_adbc_temp_options($items_type){

	$adbc_temp_options = array(
		"aDBc_temp_last_item_line_" 			. $items_type,
		"aDBc_temp_last_file_line_" 			. $items_type,
		"aDBc_temp_last_iteration_" 			. $items_type,
		"aDBc_temp_total_files_" 				. $items_type,
		"aDBc_temp_maybe_scores_"				. $items_type,
		"aDBc_temp_currently_scanning_" 		. $items_type,
		"aDBc_temp_progress_scan_"				. $items_type,
		"aDBc_temp_progress_files_preparation_"	. $items_type,
		"aDBc_temp_last_collected_file_path_" 	. $items_type,
		"aDBc_temp_items_to_scan_" 				. $items_type,
		"aDBc_temp_scan_type_" 					. $items_type,
		"aDBc_temp_current_scan_step_"			. $items_type,
	);

	return $adbc_temp_options;

}

?>