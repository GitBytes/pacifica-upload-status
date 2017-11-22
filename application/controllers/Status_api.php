<?php
/**
 * Pacifica.
 *
 * Pacifica is an open-source data management framework designed
 * for the curation and storage of raw and processed scientific
 * data. It is based on the [CodeIgniter web framework](http://codeigniter.com).
 *
 *  The Pacifica-upload-status module provides an interface to
 *  the ingester status reporting backend, allowing users to view
 *  the current state of any uploads they may have performed, as
 *  well as enabling the download and retrieval of that data.
 *
 * PHP Version 5
 *
 * @package Pacifica-upload-status
 * @author  Ken Auberry  <Kenneth.Auberry@pnnl.gov>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License
 *
 * @link http://github.com/EMSL-MSC/pacifica-upload-status
 */
require_once 'Baseline_api_controller.php';

/**
 * Status API is a CI Controller class that extends Baseline_controller.
 *
 * The *Status API* class is the main entry point into the status
 * website. It provides overview pages that summarize a filtered
 * set of all uploads, as well as a single-transaction view
 * that shows the status of a specified upload transaction
 *
 * @category Class
 * @package  Pacifica-upload-status
 * @author   Ken Auberry  <Kenneth.Auberry@pnnl.gov>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 *
 * @link http://github.com/EMSL-MSC/pacifica-upload-status
 */
class Status_api extends Baseline_api_controller
{
    /**
     * Constructor.
     *
     * Defines the base set of scripts/CSS files for every
     * page load
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Status_api_model', 'status');
        $this->load->model('Myemsl_api_model', 'myemsl');
        // $this->load->model('Cart_model', 'cart');
        $this->load->helper(
            array(
                'url', 'html', 'myemsl_api', 'file_info', 'theme', 'time'
            )
        );

        $this->load->helper(
            array(
                'inflector', 'item', 'form', 'network', 'cookie',
            )
        );
        $this->load->library(array('table'));
        $this->status_list = array(
          0 => 'Submitted', 1 => 'Received', 2 => 'Processing',
          3 => 'Verified', 4 => 'Stored', 5 => 'Available', 6 => 'Archived',
        );

        $this->last_update_time = get_last_update(APPPATH);
        $this->page_data['site_identifier'] = $this->config->item('site_identifier');
        $this->page_data['script_uris'] = array(
            '/resources/scripts/spinner/spin.min.js',
            '/resources/scripts/fancytree/dist/jquery.fancytree-all.js',
            '/resources/scripts/jquery-crypt/jquery.crypt.js',
            '/project_resources/scripts/myemsl_file_download.js',
            '/resources/scripts/select2-4/dist/js/select2.js',
            '/resources/scripts/bootstrap-daterangepicker/daterangepicker.js'
        );
        $this->page_data['css_uris'] = array(
            '/resources/scripts/bootstrap/css/bootstrap.css',
            '/resources/scripts/bootstrap-daterangepicker/daterangepicker.css',
            '/resources/scripts/fancytree/dist/skin-lion/ui.fancytree.min.css',
            '/project_resources/stylesheets/combined.css',
            '/resources/scripts/select2-4/dist/css/select2.css',
            '/resources/stylesheets/file_directory_styling.css',
            '/project_resources/stylesheets/cart.css'
        );
        $this->page_data['load_prototype'] = FALSE;
        $this->page_data['load_jquery'] = TRUE;
        $this->page_data['status_list'] = $this->status_list;
        $this->overview_template = $this->config->item('main_overview_template') ?: "emsl_mgmt_view.html";

    }

    /**
     * Primary index redirect method.
     *
     * @return void
     */
    public function index()
    {
        redirect('/overview');
    }

    /**
     * Full page generating version of overview
     *
     * @param string $proposal_id   id of the proposal to display
     * @param string $instrument_id id of the instrument to display
     * @param string $starting_date starting time period
     * @param string $ending_date   ending time period
     *
     * @return void
     */
    public function overview(
        $proposal_id = '',
        $instrument_id = '',
        $starting_date = '',
        $ending_date = ''
    )
    {
        $proposal_id = $proposal_id ?: get_cookie('last_proposal_selector');
        $instrument_id = $instrument_id ?: get_cookie('last_instrument_selector');
        // $time_period = $time_period ?: get_cookie('last_timeframe_selector');
        $starting_date = $starting_date ?: get_cookie('last_starting_date_selector');
        $ending_date = $ending_date ?: get_cookie('last_ending_date_selector');
        $proposal_id = $proposal_id != 'null' ? $proposal_id : 0;
        $instrument_id = $instrument_id != 'null' ? $instrument_id : 0;

        if(!$starting_date || !$ending_date) {
            $today = new DateTime();
            if(!$ending_date) {
                $ending_date = $today->format('Y-m-d');
            }
            if(!$starting_date) {
                $today->modify('-30 days');
                $starting_date = $today->format('Y-m-d');
            }
        }

        $view_name = $this->overview_template;
        $this->page_data['page_header'] = 'Status Reporting';
        $this->page_data['title'] = 'Overview';
        $this->page_data['informational_message'] = '';
        $this->page_data['css_uris']
            = load_stylesheets(
                $this->page_data['css_uris'],
                array(
                    '/project_resources/stylesheets/selector.css',
                )
            );
        $this->page_data['script_uris']
            = load_scripts(
                $this->page_data['script_uris'],
                array(
                    '/project_resources/scripts/overview.js',
                )
            );

        $this->benchmark->mark('get_user_info_from_ws_start');
        $full_user_info = $this->myemsl->get_user_info();
        $this->benchmark->mark('get_user_info_from_ws_end');

        $proposal_list = array();
        if (array_key_exists('proposals', $full_user_info)) {
            foreach ($full_user_info['proposals'] as $prop_id => $prop_info) {
                if (array_key_exists('title', $prop_info)) {
                    $proposal_list[$prop_id] = $prop_info['title'];
                }
            }
            if(array_key_exists('proposal_list', $this->page_data)) {
                $this->page_data['proposal_list'] = $this->page_data['proposal_list'] + $proposal_list;
            }else{
                $this->page_data['proposal_list'] = $proposal_list;
            }
            ksort($this->page_data['proposal_list']);
        }

        $js = "var initial_proposal_id = '{$proposal_id}';
                var initial_instrument_id = '{$instrument_id}';
                var initial_starting_date = '{$starting_date}';
                var initial_ending_date = '{$ending_date}';
                var email_address = '{$this->email}';
                var lookup_type = 't';
                var initial_instrument_list = [];
                var cart_access_url_base = '{$this->config->item('external_cart_url')}';
                ";

        $this->page_data['selected_proposal'] = $proposal_id;
        $this->page_data['starting_date'] = $starting_date;
        $this->page_date['ending_date'] = $ending_date;
        $this->page_data['instrument_id'] = $instrument_id;
        $this->page_data['js'] = $js;

        $this->overview_worker(
            $proposal_id, $instrument_id,
            $starting_date, $ending_date, $view_name
        );
    }

    /**
     * Full page generating version of overview for external consumption
     *
     * @param string $proposal_id   id of the proposal to display
     * @param string $instrument_id id of the instrument to display
     * @param string $starting_date starting time period
     * @param string $ending_date   ending time period
     *
     * @return void
     */
    public function overview_insert(
        $proposal_id = FALSE,
        $instrument_id = FALSE,
        $starting_date = FALSE,
        $ending_date = FALSE
    )
    {
        if(!$proposal_id || !$instrument_id) {
            $message = "Some parameters missing. Please supply values for: ";
            $criteria_array = array();
            if(!$proposal_id) $criteria_array[] = "proposal";
            if(!$instrument_id) $criteria_array[] = "instrument";
            $message .= implode(" and ", $criteria_array);
            http_response_code(412);
            print "<p class=\"error_msg\">{$message}</p>";
            return;
        }

        if(!$starting_date || !strtotime($starting_date) || !$ending_date || !strtotime($ending_date)) {
            $today = new DateTime();
            if(!$ending_date || !strtotime($ending_date)) {
                $ending_date = $today->format('Y-m-d');
            }
            if(!$starting_date || !strtotime($starting_date)) {
                $today->modify('-30 days');
                $starting_date = $today->format('Y-m-d');
            }
        }


        $this->page_data['proposal_info'] = get_proposal_abstract($proposal_id);
        $this->page_data['instrument_info'] = get_instrument_details($instrument_id);
        $this->page_data['proposal_list'][$proposal_id] = $this->page_data['proposal_info']['title'];


        $this->page_data['script_uris'][] = '/project_resources/scripts/external.js';

        $this->overview($proposal_id, $instrument_id, $starting_date, $ending_date);
    }


    /**
     * Primary index page shows overview of status for that user.
     *
     * @param string $proposal_id   id of the proposal to display
     * @param string $instrument_id id of the instrument to display
     * @param string $starting_date starting time period
     * @param string $ending_date   ending time period
     * @param string $view_name     CodeIgniter view to use for formatting this information
     *
     * @return void
     */
    public function overview_worker(
        $proposal_id = '',
        $instrument_id = '',
        $starting_date = '',
        $ending_date = '',
        $view_name = 'upload_item_view.html'
    )
    {
        $time_period_empty = TRUE;
        if (isset($instrument_id) && intval($instrument_id) != 0
            && isset($proposal_id) && intval($proposal_id) != 0
        ) {

            $message = "No data available for this instrument and proposal in the specified time period";
            //all criteria set, proceed with load
            $now = new DateTime();
            $end = strtotime($ending_date) ? new DateTime($ending_date) : new DateTime();
            $end_time = $end->modify('+1 day')->modify('-1 sec')->format('Y-m-d H:i:s');
            $clone_start = clone $now;
            $clone_start->modify("-30 days");
            $start = strtotime($starting_date) ? new DateTime($starting_date) : $clone_start;
            $start_time = $start->format('Y-m-d');
            $transaction_list = $this->status->get_transactions(
                $instrument_id, $proposal_id, $start_time, $end_time
            );
            $file_size_totals = array();
            foreach($transaction_list['transactions'] as $transaction_id => $transaction_info){
                $file_size_totals[$transaction_id] = $transaction_info['file_size_bytes'];
                $message = "";
                $time_period_emtpy = FALSE;
            }
            $transaction_list['file_size_totals'] = $file_size_totals;
            $results = array(
                'transaction_list' => $transaction_list,
                'time_period_empty' => $time_period_empty,
                'message' => $message,
            );
        } else {
            $results = array(
                'transaction_list' => array(),
                'time_period_empty' => TRUE,
                'message' => 'No data available for this instrument and proposal',
            );
        }
        $this->page_data['cart_data'] = array('carts' => array());
        if (!empty($results) && array_key_exists('transaction_list', $results)) {
            if (array_key_exists('transactions', $results['transaction_list'])) {
                krsort($results['transaction_list']['transactions']);
            }
            if (array_key_exists('times', $results['transaction_list'])) {
                krsort($results['transaction_list']['times']);
            }
        }
        $this->page_data['selected_proposal_id'] = $proposal_id;
        $this->page_data['selected_instrument_id'] = $instrument_id;
        $this->page_data['enable_breadcrumbs'] = FALSE;
        $this->page_data['status_list'] = $this->status_list;
        $this->page_data['transaction_data'] = $results['transaction_list'];
        if (array_key_exists('transactions', $results['transaction_list'])
            && !empty($results['transaction_list']['transactions'])
        ) {
            $this->page_data['transaction_sizes'] = $results['transaction_list']['file_size_totals'];
        } else {
            $this->page_data['transaction_sizes'] = array();
        }
        $this->page_data['informational_message'] = $results['message'];
        $this->page_data['request_type'] = 't';

        $this->load->view($view_name, $this->page_data);
    }


    /**
     * Detail page for individual transactions.
     *
     * @param string $id id of the transaction to display
     *
     * @return void
     */
    public function view($id)
    {
        $lookup_type_description = 'Transaction';
        $lookup_type = 'transaction';
        $instrument_id = -1;
        $this->page_data['css_uris']
            = load_stylesheets(
                $this->page_data['css_uris'],
                array(
                    '/project_resources/stylesheets/view.css'
                )
            );
        $this->page_data['script_uris']
            = load_scripts(
                $this->page_data['script_uris'],
                array(
                    '/project_resources/scripts/single_item_view.js',
                    '/resources/scripts/jquery-dateFormat/jquery-dateFormat.min.js'
                )
            );

        if (!is_numeric($id) || $id < 0) {
            //that doesn't look like a real id
            //send to error page saying so
            $err_msg = 'No '.ucwords($lookup_type_description)." with the an id of ".
                    "<strong>{$id}</strong> could be found in the system";
            $this->page_data['page_header'] = "{$lookup_type_description} Not Found";
            $this->page_data['title'] = $this->page_data['page_header'];
            $this->page_data['error_message'] = $err_msg;
            $this->page_data['lookup_type_desc'] = $lookup_type_description;
            $this->page_data['lookup_type'] = $lookup_type;
            $this->load->view('status_error_page.html', $this->page_data);
        }

        $transaction_info = $this->status->get_formatted_transaction($id);
        if(sizeof($transaction_info['transactions']) == 0) {
            $last_id = $this->status->get_last_known_transaction();
            if($id >= $last_id) {
                $this->page_data['page_header'] = 'New Transaction';
                $this->page_data['title'] = 'Transaction Pending';
                $err_msg = "This transaction is still being processed by the uploader";
                $this->page_data['error_message'] = $err_msg;
                $this->page_data['js'] = "
var transaction_id = '{$id}';
$(function(){
    setInterval(function(){
        refresh();
    }, 5000);
});
var refresh = function(){
    var getter = $.get(base_url + 'ajax_api/get_latest_transaction_id', function(data){
        var last_id = data.last_transaction_id;
        if(transaction_id <= last_id){
            location.reload(true);
        }
    });
}
";
            }else{
                $this->page_data['page_header'] = 'Missing Transaction';
                $this->page_data['title'] = 'Transaction not available';
                $err_msg = "No transaction with an ID of {$id} could be found in the system";
                $this->page_data['error_message'] = $err_msg;
                $this->page_data['force_refresh'] = FALSE;
            }
            $this->page_data['lookup_type_desc'] = $lookup_type_description;
            $this->page_data['lookup_type'] = $lookup_type;
            $this->load->view('status_error_page.html', $this->page_data);

        }

        $this->page_data['page_header'] = 'Upload Report';
        $this->page_data['title'] = 'Upload Report';
        $file_size = 0;
        $inst_id = -1;
        if(array_key_exists($id, $transaction_info['transactions'])) {
            $file_size = $transaction_info['transactions'][$id]['file_size_bytes'];
            $inst_id = $transaction_info['transactions'][$id]['metadata']['instrument_id'];
        }
        $this->page_data['transaction_sizes'][] = $file_size;

        $this->page_data['transaction_data'] = $transaction_info;
        $this->page_data['cart_data'] = array(
            'carts' => array()
        );
        $this->page_data['request_type'] = 't';
        $this->page_data['enable_breadcrumbs'] = FALSE;
        $this->page_data['js'] = "var initial_inst_id = '{$inst_id}';
                            var lookup_type = 't';
                            var email_address = '{$this->email}';
                            var cart_access_url_base = '{$this->config->item('external_cart_url')}';
                            ";
        $this->page_data['show_instrument_data'] = TRUE;
        $this->load->view('single_item_view.html', $this->page_data);

    }

    /**
     * Get Lazy Load Folder.
     *
     * @return void
     */
    public function get_lazy_load_folder()
    {
        if (!$this->input->post('parent')) {
            echo '';

            return;
        }
        $node = intval(str_replace('treeData_', '', $this->input->post('parent')));
        $treelist = $this->status->get_files_for_transaction($node);
        $output_array = format_folder_object_json($treelist['treelist'], 'test');
        transmit_array_with_json_header($output_array);
    }
}
