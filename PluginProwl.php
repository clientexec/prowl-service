<?php
/**
 * Prowl Plugin FIle
 *
 * @category Services
 * @package  ClientExec
 * @author   Jason Yates <jason@clientexec.com>
 * @license  ClientExec License
 * @version  [someversion]
 * @link     http://www.clientexec.com
 */

require_once 'modules/admin/models/ServicePlugin.php';
require_once 'plugins/services/prowl/ProwlPHP.php';

/**
 * PluginProwl Action Class
 *
 * @category Services
 * @package  ClientExec
 * @author   Jason Yates <jason@clientexec.com>
 * @license  ClientExec License
 * @version  [someversion]
 * @link     http://www.clientexec.com
 */
class PluginProwl extends ServicePlugin
{
    public $hasPendingItems = false;

    var $lastRun = null;
    var $notifications = array();

    /**
     * Service getVariables Method
     *
     * @return array - list of valid variables
     */
    function getVariables()
    {
        $variables = array(
            /*T*/'Plugin Name'/*/T*/   => array(
                'type'          => 'hidden',
                'description'   => /*T*/''/*/T*/,
                'value'         => /*T*/'Prowl Push Notifications'/*/T*/,
            ),
            /*T*/'Enabled'/*/T*/       => array(
                'type'          => 'yesno',
                'description'   => /*T*/'When enabled, this service plugin pushes certain notifications from ClientExec to the Prowl application on your iPhone. Please note, Curl with SSL is required to use the Prowl service. <b>This service requires the Paid iPhone application Prowl to work</b>'/*/T*/,
                'value'         => '0',
            ),
            /*T*/'Prowl API Keys'/*/T*/  => array(
                'type'          => 'textarea',
                'description'   => /*T*/'Enter the API key for your Prowl account (separate multiple keys with a comma)'/*/T*/,
                'value'         => ''
            ),
            /*T*/'Notify of New Orders'/*/T*/  => array(
                'type'          => 'yesno',
                'description'   => /*T*/'Select Yes to be notified when a new order is placed.'/*/T*/,
                'value'         => '1',
            ),
            /*T*/'Notify of New High Priority Tickets'/*/T*/  => array(
                'type'          => 'yesno',
                'description'   => /*T*/'Select Yes to be notified when a new High Priority Ticket is recieved.'/*/T*/,
                'value'         => '1',
            ),
            /*T*/'Run schedule - Minute'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '5',
                'helpid'        => '8',
            ),
            /*T*/'Run schedule - Hour'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'Run schedule - Day'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'Run schedule - Month'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'Run schedule - Day of the week'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number in range 0-6 (0 is Sunday) or a 3 letter shortcut (e.g. sun)'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'LastRun'/*/T*/  => array(
                'type'          => 'hidden',
                'description'   => /*T*/'Timestamp of last run. Used to determine new items to push'/*/T*/,
                'value'         => ''
            ),
        );

        return $variables;
    }

    /**
     * Function that is called by the service when executing via Cron
     *
     * @return null
     */
    function execute()
    {

        // service execution can take a while
        @set_time_limit(0);

        // Grab the last run, make one up if the LastRun isn't set to avoid sending alerts for every item.
        $this->lastRun = $this->settings->get('plugin_prowl_LastRun');
        ($this->lastRun == '')? $this->lastRun = time() : false;
        $this->lastRun = date('Y-m-d G:i:s', $this->lastRun);

        // Get the orders
        $this->getNewOrders();

        // Get the tickets
        $this->getHighPriTickets();

        // Get the list of Prowl API key's and start making some alerts
        $APIKeys = $this->settings->get('plugin_prowl_Prowl API Keys');
        $APIKeys = explode(',', $APIKeys);

        foreach($APIKeys AS $key) {

            // Start the prowl API
            $prowl = new Prowl($key);

            //Loop the notifications to send
            foreach($this->notifications AS $notification) {

                $prowl->push(array(
                        'application'=>'ClientExec',
                        'event'=>$notification['event'],
                        'description'=>$notification['description'],
                        'priority'=>0
                    ),true);
            }
        }

        // Set the last run
        $this->settings->deleteValue('plugin_prowl_LastRun');
        $this->settings->insertValue('plugin_prowl_LastRun', time(), '', false, true);
    }

    /**
     * Function to save a list of new high priority tickets
     *
     * @return null
     */
    function getHighPriTickets()
    {
        // Check if we are doing this bit
        if($this->settings->get('plugin_prowl_Notify of New High Priority Tickets')) {

            // Start querying
            $query = "SELECT id FROM troubleticket WHERE priority = '1' AND datesubmitted >= '".$this->lastRun."'";
            $result = $this->db->query($query);
            while ($row = $result->fetch()) {

                $this->notifications[] = array('event' => 'High Priority Ticket', 'description' => "A new High Priority Ticket has been submitted #".$row['id']);
            }
        }
    }

    /**
     * Function to save a list of new orders
     *
     * @return null
     */
    function getNewOrders()
    {
        // Check if we are doing this bit
        if($this->settings->get('plugin_prowl_Notify of New Orders')) {

            // Start querying
            $query = "SELECT id FROM domains WHERE dateActivated >= '".$this->lastRun."'";
            $result = $this->db->query($query);
            while ($row = $result->fetch()) {

                $this->notifications[] = array('event' => 'New Order', 'description' => "A new order has been recieved #".$row['id']);
            }
        }
    }

    function output()
    {
    }

    function dashboard()
    {
    }
}
?>
