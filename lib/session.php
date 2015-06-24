<?php

/**
 * Session handling
 * @package framework
 * @subpackage session
 */

/**
 * PHP Sessions that extend the base session class
 */
class Hm_PHP_Session extends Hm_Session {

    /* cookie name for sessions */
    protected $cname = 'PHPSESSID';

    /**
     * Check for an existing session or a new user/pass login request
     * @param object $request request details
     * @param string $user username
     * @param string $pass password
     * @return bool
     */
    public function check($request, $user=false, $pass=false) {
        if ($user && $pass) {
            if ($this->auth($user, $pass)) {
                $this->set_key($request);
                $this->loaded = true;
                $this->start($request);
                $this->set_fingerprint($request);
                $this->save_auth_detail();
                $this->just_started();
            }
            else {
                Hm_Msgs::add("ERRInvalid username or password");
            }
        }
        elseif (array_key_exists($this->cname, $request->cookie)) {
            $this->get_key($request);
            $this->start($request);
            $this->check_fingerprint($request);
        }
        if ($this->is_active() && $request->invalid_input_detected) {
            Hm_Debug::add(sprintf('Invalid input fields: %s', implode(',', $request->invalid_input_fields)));
            $this->destroy($request);
        }
        return $this->is_active();
    }

    /**
     * Call the configured authentication method to check user credentials
     * @param string $user username
     * @param string $pass password
     * @return bool true if the authentication was successful
     */
    public function auth($user, $pass) {
        $this->load_auth_mech();
        return $this->auth_mech->check_credentials($user, $pass);
    }

    /**
     * Save auth detail if i'ts needed (mech specific)
     * @return void
     */
    public function save_auth_detail() {
        $this->auth_mech->save_auth_detail($this);
    }

    /**
     * Call the configuration authentication method to change the user password
     * @param string $user username
     * @param string $pass password
     * @return bool true if the password was changed
     */
    public function change_pass($user, $pass) {
        $this->load_auth_mech();
        return $this->auth_mech->change_pass($user, $pass);
    }

    /**
     * Call the configuration authentication method to create an account
     * @param object $request request details
     * @param string $user username
     * @param string $pass password
     * @return bool true if the account was created
     */
    public function create($request, $user, $pass) {
        $this->load_auth_mech();
        if ($this->auth_mech->create($user, $pass)) {
            return $this->check($request, $user, $pass);
        }
        return false;
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request) {
        if (array_key_exists($this->cname, $request->cookie)) {
            session_id($request->cookie[$this->cname]);
        }
        list($secure, $path, $domain) = $this->set_session_params($request);
        session_set_cookie_params(0, $path, $domain, $secure);
        Hm_Functions::session_start();

        if ($request->type == 'HTTP' && ! array_key_exists( 'logout', $request->post)) {
            session_regenerate_id(true);
            $this->cname = session_id();
        }
        if (array_key_exists('data', $_SESSION)) {
            $data = $this->plaintext($_SESSION['data']);
            if (is_array($data)) {
                $this->data = $data;
            }
            elseif (!$this->loaded) {
                $this->destroy($request);
                Hm_Debug::add('Mismatched session level encryption key');
            }
        }
        $this->active = true;
    }

    /**
     * Setup the cookie params for a session cookie
     * @param object $request request details
     * @return array list of cookie fields
     */
    public function set_session_params($request) {
        $domain = false;
        $path = false;
        if ($request->tls) {
            $secure = true;
        }
        else {
            $secure = false;
        }
        if (isset($request->path)) {
            $path = $request->path;
        }
        if (array_key_exists('SERVER_NAME', $request->server) && strtolower($request->server['SERVER_NAME']) != 'localhost') {
            $domain = $request->server['SERVER_NAME'];
        }
        return array($secure, $path, $domain);
    }

    /**
     * Return a session value, or a user settings value stored in the session
     * @param string $name session value name to return
     * @param mixed $default value to return if $name is not found
     * @param bool $user if true, only search the user_data section of the session
     * @return mixed the value if found, otherwise $default
     */
    public function get($name, $default=false, $user=false) {
        if ($user) {
            return array_key_exists('user_data', $this->data) && array_key_exists($name, $this->data['user_data']) ? $this->data['user_data'][$name] : $default;
        }
        else {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    /**
     * Save a value in the session
     * @param string $name the name to save
     * @param string $value the value to save
     * @param bool $user if true, save in the user_data section of the session
     * @return void
     */
    public function set($name, $value, $user=false) {
        if ($user) {
            $this->data['user_data'][$name] = $value;
        }
        else {
            $this->data[$name] = $value;
        }
    }

    /**
     * Delete a value from the session
     * @param string $name name of value to delete
     * @return void
     */
    public function del($name) {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        if ($this->active) {
            if (!$this->session_closed) {
                $this->save_data();
            }
            $this->active = false;
        }
    }

    /**
     * Write session data to avoid locking, keep session active, but don't allow writing
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /**
     * Save session data
     * @return void
     */
    public function save_data() {
        $enc_data = $this->ciphertext($this->data);
        $_SESSION = array('data' => $enc_data);
        session_write_close();
        $_SESSION = array();
    }

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    public function destroy($request) {
        session_unset();
        @session_destroy();
        $params = session_get_cookie_params();
        $this->secure_cookie($request, $this->cname, '', time()-3600, $params['path'], $params['domain']);
        $this->secure_cookie($request, 'hm_id', '', time()-3600);
        $this->secure_cookie($request, 'hm_reload_folders', 0, time()-3600);
        $this->active = false;
    }
}

/**
 * This session class uses a PDO compatible DB to manage session data. It does not
 * use PHP session handlers at all and is a completely indenpendant session system.
 */
class Hm_DB_Session extends Hm_PHP_Session {

    /* session cookie name */
    protected $cname = 'hm_session';

    /* session key */
    public $session_key = '';

    /* DB handle */
    protected $dbh = false;

    /**
     * Create a new session
     * @return bool true on success
     */
    public function insert_session_row() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("insert into hm_user_session values(?, ?, current_date)");
            $enc_data = $this->ciphertext($this->data);
            return $sql->execute(array($this->session_key, $enc_data));
        }
        return false;
    }

    /**
     * Connect to the configured DB
     * @return bool true on success
     */
    public function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request) {
        if ($this->connect()) {
            if ($this->loaded) {
                $this->start_new_session($request);
            }
            else if (!array_key_exists($this->cname, $request->cookie)) {
                $this->destroy($request);
            }
            else {
                $this->start_existing_session($request, $request->cookie[$this->cname]);
            }
        }
    }

    /**
     * Start a new session
     * @param object $request request details
     * @return void
     */
    public function start_new_session($request) {
        $this->session_key = Hm_Crypt::unique_id(); 
        $this->secure_cookie($request, $this->cname, $this->session_key, 0);
        if ($this->insert_session_row()) {
            $this->active = true;
        }
    }

    /**
     * Continue an existing session
     * @param object $request request details
     * @param string $key session key
     * @return void
     */
    public function start_existing_session($request, $key) {
        $this->session_key = $key;
        $sql = $this->dbh->prepare('select data from hm_user_session where hm_id=?');
        if ($sql->execute(array($this->session_key))) {
            $results = $sql->fetch();
            if (is_array($results) && array_key_exists('data', $results)) {
                $data = $this->plaintext($results['data']);
                if (is_array($data)) {
                    $this->active = true;
                    $this->data = $data;
                }
            }
        }
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        if (!$this->session_closed) {
            $this->save_data();
        }
        $this->active = false;
    }

    /**
     * Write session data to the db
     * @return void
     */
    public function save_data() {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("update hm_user_session set data=? where hm_id=?");
            $enc_data = $this->ciphertext($this->data);
            return $sql->execute(array($enc_data, $this->session_key));
        }
        return false;
    }

    /**
     * Close a session early, but don't destroy it
     * @return void
     */
    public function close_early() {
        $this->session_closed = true;
        $this->save_data();
    }

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    public function destroy($request) {
        if ($this->dbh) {
            $sql = $this->dbh->prepare("delete from hm_user_session where hm_id=?");
            $sql->execute(array($this->session_key));
        }
        $this->secure_cookie($request, $this->cname, '', time()-3600);
        $this->secure_cookie($request, 'hm_id', '', time()-3600);
        $this->active = false;
    }
}
