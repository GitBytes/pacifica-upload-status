<?php
/**
 * Pacifica
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
 * @link    http://github.com/EMSL-MSC/pacifica-upload-status
 */

require_once 'Baseline_api_controller.php';

/**
 * Cart is a CI Controller class that extends Baseline_controller
 *
 * The *Cart* class interacts with the MyEMSL Cart web API to
 * allow download of archived data, as well as generating proper
 * cart_token entities to allow for multi-file download specifications.
 *
 * @category Class
 * @package  Pacifica-upload-status
 * @author   Ken Auberry  <Kenneth.Auberry@pnnl.gov>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://github.com/EMSL-MSC/pacifica-upload-status
 */
class Cart_api extends Baseline_api_controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Cart_api_model', 'cart');
        $this->load->helper(array('url', 'network', 'item'));
    }

    /**
     * Retrieve the list of active carts owned by this user
     *
     * @return void sends out JSON text to browser
     *
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function listing()
    {
        $accept = $this->input->get_request_header('Accept');
        $cart_list = $this->cart->cart_status();
        if(stristr(strtolower($accept), 'json')) {
            //looks like a json request
            transmit_array_with_json_header($cart_list);
        }else{
            if(empty($cart_list)) {
            }else{
                //let's assume that they want html
                $this->load->view('cart_status_insert_view.html', $cart_list);
            }
        }
    }

    /**
     *  Create a new download cart
     *
     *  @return void
     *
     *  @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function create()
    {
        $req_method = array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : "GET";
        if($req_method != "POST") {
            //return info on how to use this function
            echo "That's not how you use this function!!!";
            exit();
        }
        $submit_block = json_decode($this->input->raw_input_stream, TRUE);
        if(empty($submit_block)) {
            //bad json-block or empty post body
            echo "Hey! There's no real data here!";
        }
        // var_dump($this->input->request_headers());
        $cart_uuid_info = $this->cart->cart_create($this->input->raw_input_stream);
        transmit_array_with_json_header($cart_uuid_info);
    }

    /**
     * Deletes existing cart instances
     *
     * @param string $cart_uuid SHA256 hash identifier for the cart
     *
     * @return void
     *
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function delete($cart_uuid)
    {
        $req_method = array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : "GET";
        if($req_method != "DELETE") {
            echo "That's not how you use this function!!!";
            exit();
        }
        $status_message = $this->cart->cart_delete($cart_uuid);
        $success = FALSE;
        if ($status_message / 100 == 2) {
            //looks like it went through ok
            $success = TRUE;
        }
        $success_message = $success ? "" : " not";
        $ret_message = array(
            'message' => "The cart was{$success_message} successfully deleted "
        );
        transmit_array_with_json_header($ret_message, "", $success);
    }

}