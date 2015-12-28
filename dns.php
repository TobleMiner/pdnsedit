<?php namespace DNS;

    require_once('config.php');

    use \mysqli as mysqli;
    use \Config_MySql as Config_MySql;

    /**
     * Represents a domain entry
     * Only supports NATIVE domain entries
     */
    class Domain
    {
        static function get_all()
        {
            $mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            $query = $mysqli->prepare('SELECT id, name FROM domains');
            $query->execute();
            $id = null;
            $name = null;
            $domains = array();
            $query->bind_result($id, $name);
            while($query->fetch())
            {
                $domain = new Domain($id, $name);
                $domains[] = $domain;
            }
            return $domains;
        }

        static function from_name($name)
        {
            $mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            $query = $mysqli->prepare('SELECT id FROM domains WHERE name=?');
            $query->bind_param('s', $name);
            $query->execute();
            $id = null;
            $query->bind_result($id);
            if(!$query->fetch())
                return false;
            return new Domain($id, $name);
        }

        public $id = null;
        public $domain = null;
        public static $mysqli = null;

        function __construct($id, $domain=null)
        {
            $this->id = $id;
            Domain::$mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            if(!$domain)
            {
                $mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                    Config_MySql::$pass, Config_MySql::$db);
                $query = $mysqli->prepare('SELECT name FROM domains WHERE id=?');
                $query->bind_param('i', $id);
                $query->execute();
                $query->bind_result($domain);
                if(!$query->fetch())
                    throw new Exception("Unknown domain id", 1);
            }
            $this->domain = $domain;
        }

        function get_id()
        {
            return $this->id;
        }

        function get_all_records()
        {
            return Record::all_for_domain($this);
        }

        function add()
        {
            $query = Domain::$mysqli->prepare("INSERT INTO domains (name,type) VALUES (?,'NATIVE')");
            $query->bind_param('s', $this->domain);
            $query->execute();
        }

        function save()
        {
            $query = Domain::$mysqli->prepare("UPDATE domains SET name=? type='NATIVE' WHERE id=?");
            $query->bind_param('si', $this->domain, $this->get_id);
            $query->execute();
        }

        function delete()
        {
            $query = Domain::$mysqli->prepare("DELETE FROM domains WHERE id=?");
            $query->bind_param('i', $this->get_id());
            $query->execute();
            $query = Domain::$mysqli->prepare("DELETE FROM records WHERE domain_id=?");
            $query->bind_param('i', $this->get_id());
            $query->execute();
        }
    }

    /**
     * Represents a dns record
     */
    abstract class Record
    {
        static function get_record_types()
        {
            $names = array();
            $names[] = 'A';
            $names[] = 'AAAA';
            $names[] = 'SOA';
            $names[] = 'PTR';
            $names[] = 'MX';
            $names[] = 'NS';
            $names[] = 'CNAME';
            $names[] = 'SRV';
            return $names;
        }

        static function from_id($id)
        {
            $mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            $query = $mysqli->prepare('SELECT id, domain_id, name, content, type, ttl, prio
                FROM records WHERE id=?');
            $query->bind_param('i', $id);
            $query->execute();
            $domain_id = null;
            $name = null;
            $value = null;
            $type = null;
            $ttl = null;
            $priority = null;
            $query->bind_result($id, $domain_id, $name, $value, $type, $ttl, $priority);
            $query->fetch();
            $domain = new Domain($domain_id);
            $class = '\\DNS\\'.$type.'_record';
            return new $class($domain, $id, $name, $value, $ttl, $priority);
        }

        static function all_for_domain($domain)
        {
            $mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            $query = $mysqli->prepare('SELECT id, name, content, type, ttl, prio
                FROM records WHERE domain_id=?');
            $query->bind_param('i', $domain->get_id());
            $query->execute();
            $id = null;
            $name = null;
            $value = null;
            $type = null;
            $ttl = null;
            $priority = null;
            $records = array();
            $query->bind_result($id, $name, $value, $type, $ttl, $priority);
            while($query->fetch())
            {
                $class = '\\DNS\\'.$type.'_record';
                $record = new $class($domain, $id, $name, $value, $ttl, $priority);
                $records[] = $record;
            }
            return $records;
        }

        public $id = null;
        public $domain = null;
        public $name = null;
        public $value = null;
        public $ttl = null;
        public $priority = null;
        public $type = "NOPE";
        public $error = null;

        function __construct($domain, $id, $name, $value, $ttl=3600, $priority=0)
        {
            Domain::$mysqli = new mysqli(Config_MySql::$host, Config_MySql::$user,
                Config_MySql::$pass, Config_MySql::$db);
            $this->id = $id;
            $this->domain = $domain;
            $this->name = $name;
            $this->value = $value;
            $this->ttl = $ttl;
            $this->priority = $priority;
        }

        abstract function validate();

        function add()
        {
            $query = Domain::$mysqli->prepare("INSERT INTO records
                (domain_id, name, type, content, ttl, prio, auth) VALUES
                (?, ?, ?, ?, ?, ?, 1)");
            $query->bind_param('isssii', $this->domain->get_id(), $this->name,
                $this->type, $this->value, $this->ttl, $this->priority);
            $query->execute();
        }

        function save()
        {
            $query = Domain::$mysqli->prepare("UPDATE records SET
                domain_id=?, name=?, content=?, type=?, ttl=?, prio=? WHERE
                id=?");
            $query->bind_param('isssiii', $this->domain->get_id(), $this->name,
                $this->value, $this->type, $this->ttl, $this->priority, $this->id);
            $query->execute();
        }

        function delete()
        {
            $query = Domain::$mysqli->prepare("DELETE FROM records WHERE id=?");
            $query->bind_param('i', $this->id);
            $query->execute();
        }
    }

    /**
     * DNS A record
     */
    class A_record extends Record
    {
        public $type = 'A';

        public function validate()
        {
            if(filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
                return true;
            $this->error = 'Please enter a valid IPv4 address';
            return false;
        }
    }

    /**
     * DNS AAAA record
     */
    class AAAA_record extends Record
    {
        public $type = 'AAAA';

        public function validate()
        {
            if(filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
                return true;
            $this->error = 'Please enter a valid IPv6 address';
            return false;
        }
    }

    /**
     * DNS NS record
     */
    class NS_record extends Record
    {
        public $type = 'NS';

        public function validate()
        {
            if(filter_var($this->value, FILTER_VALIDATE_IP) ||
                true)
                return true;
            $this->error = 'Please enter either a valid domain or IP address';
            return false;
        }
    }

    /**
     * DNS SOA record
     */
    class SOA_record extends Record
    {
        public $type = 'SOA';

        public function validate()
        {
            //Will probably write validator some time later
            return true;
        }
    }

    /**
     * DNS PTR record
     */
    class PTR_record extends Record
    {
        public $type = 'PTR';

        public function validate()
        {
            //Will probably write validator some time later
            return true;
        }
    }

    /**
     * DNS MX record
     */
    class MX_record extends Record
    {
        public $type = 'MX';

        public function validate()
        {
            //Will probably write validator some time later
            return true;
        }
    }

    /**
     * DNS SRV record
     */
    class SRV_record extends Record
    {
        public $type = 'SRV';

        public function validate()
        {
            //Will probably write validator some time later
            return true;
        }
    }

    /**
     * DNS CNAME record
     */
    class CNAME_record extends Record
    {
        public $type = 'CNAME';

        public function validate()
        {
            //Will probably write validator some time later
            return true;
        }
    }
?>
