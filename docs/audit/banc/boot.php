<?php
namespace {
// Amorce minimale : $DB branché sur la base de stress PostgreSQL, compteur de requêtes,
// bouchons des quelques API Moodle appelées par les fonctions testées.
define('MOODLE_INTERNAL', true);
define('PHPUNIT_TEST', false);
define('DAYSECS', 86400); define('HOURSECS', 3600);
define('SQL_PARAMS_NAMED', 1); define('SQL_PARAMS_QM', 2); define('MUST_EXIST', 2); define('IGNORE_MISSING', 0);
define('MEMORY_HUGE', -1); define('MEMORY_EXTRA', -2);
// Racine du dépôt : $CFG->dirroot . '/mod/stage' désigne le module testé.
$CFG = (object) ['dirroot' => dirname(__DIR__, 3)];
class core_text { static function strlen($s) { return mb_strlen($s); } static function strtolower($s) { return mb_strtolower($s); } }
class core_date { static function get_user_timezone_object() { return new DateTimeZone('Europe/Paris'); } }
class core_php_time_limit { static function raise() {} }
function raise_memory_limit($l) {}
function get_string($k, $c = '', $a = null) { return $k; }
function userdate($t, $f = '') { return date('Y-m-d', $t); }
function usergetmidnight($t) { $d = new DateTime('@' . $t); $d->setTimezone(new DateTimeZone('Europe/Paris')); $d->setTime(0, 0); return $d->getTimestamp(); }
function fullname($u) { return $u->firstname . ' ' . $u->lastname; }
class moodle_url { function __construct(...$a) {} }
function stage_paging_bar_stub() { return ''; }
class core_user_fields_stub {}
}
namespace core_user {
class fields { static function get_name_fields() { return ['firstname', 'lastname']; } }
}
namespace mod_stage\event {
class entry_created {}
}
namespace core\lock {
class lock_config { static function get_lock_factory($t) { return new class { function get_lock($r, $t) { return new class { function release() {} }; } }; } }
}
namespace {
class fake_db {
    public $pdo; public $queries = 0; public $log = [];
    function __construct() { $this->pdo = new PDO('pgsql:host=127.0.0.1;dbname=stress', 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    private function run($sql, $params, $from = 0, $num = 0) {
        $this->queries++;
        $sql = preg_replace('/\{(\w+)\}/', 'mdl_$1', $sql);
        if ($num) { $sql .= " LIMIT " . (int) $num . " OFFSET " . (int) $from; }
        // Paramètres positionnels ou nommés, comme les accepte Moodle.
        if ($params && array_keys($params) !== range(0, count($params) - 1)) {
            $p = []; foreach ($params as $k => $v) { $p[':' . $k] = $v; } $params = $p;
        } else { $params = array_values($params); }
        $st = $this->pdo->prepare($sql); $st->execute($params); return $st;
    }
    private function keyed($st) { $r = []; while ($o = $st->fetch(PDO::FETCH_OBJ)) { $r[(string) array_values((array) $o)[0]] = $o; } return $r; }
    private function where(array $cond) { $w = []; $p = []; foreach ($cond as $k => $v) { $w[] = "$k = :c_$k"; $p["c_$k"] = $v; } return [$w ? implode(' AND ', $w) : '1=1', $p]; }
    function get_records_sql($sql, array $params = [], $from = 0, $num = 0) { return $this->keyed($this->run($sql, $params, $from, $num)); }
    function get_recordset_sql($sql, array $params = [], $from = 0, $num = 0) { return new fake_rs($this->get_records_sql($sql, $params, $from, $num)); }
    function get_records_select($t, $where, array $params = [], $sort = '', $fields = '*', $from = 0, $num = 0) { return $this->get_records_sql("SELECT $fields FROM {{$t}} WHERE " . ($where ?: '1=1') . ($sort ? " ORDER BY $sort" : ''), $params, $from, $num); }
    function get_recordset_select($t, $where, array $params = [], $sort = '', $fields = '*') { return new fake_rs($this->get_records_select($t, $where, $params, $sort, $fields)); }
    function get_records($t, array $cond = [], $sort = '', $fields = '*') { [$w, $p] = $this->where($cond); return $this->get_records_select($t, $w, $p, $sort, $fields); }
    function get_record($t, array $cond, $fields = '*', $strict = 0) { $r = $this->get_records($t, $cond, '', $fields); return $r ? reset($r) : false; }
    function get_field($t, $f, array $cond) { $r = $this->get_record($t, $cond, $f); return $r ? $r->$f : false; }
    function get_fieldset_select($t, $f, $where, array $params = []) { return $this->run("SELECT $f FROM {{$t}} WHERE $where", $params)->fetchAll(PDO::FETCH_COLUMN); }
    function get_fieldset_sql($sql, array $params = []) { return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN); }
    function get_records_sql_menu($sql, array $params = []) { return $this->run($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR); }
    function count_records_sql($sql, array $params = []) { return (int) $this->run($sql, $params)->fetchColumn(); }
    function record_exists_sql($sql, array $params = []) { return (bool) $this->run($sql, $params, 0, 1)->fetch(); }
    function record_exists_select($t, $where, array $params = []) { return $this->record_exists_sql("SELECT 1 FROM {{$t}} WHERE $where", $params); }
    function get_in_or_equal($items, $type = SQL_PARAMS_QM, $prefix = 'param', $equal = true) {
        $items = array_values((array) $items); static $n = 0;
        if ($type === SQL_PARAMS_NAMED) { $p = []; foreach ($items as $v) { $p[$prefix . (++$n)] = $v; } $sql = ($equal ? 'IN' : 'NOT IN') . ' (:' . implode(', :', array_keys($p)) . ')'; return [$sql, $p]; }
        return [($equal ? 'IN' : 'NOT IN') . ' (' . implode(',', array_fill(0, count($items), '?')) . ')', $items];
    }
    function get_field_sql($sql, array $params = []) { return $this->run($sql, $params)->fetchColumn(); }
    function delete_records_select($t, $where, array $params = []) { $this->run("DELETE FROM {{$t}} WHERE $where", $params); return true; }
    function delete_records($t, array $cond = []) { [$w, $p] = $this->where($cond); return $this->delete_records_select($t, $w, $p); }
    function get_field_select($t, $f, $where, array $params = []) { return $this->run("SELECT $f FROM {{$t}} WHERE $where", $params)->fetchColumn(); }
    function execute($sql, array $params = []) { $this->run($sql, $params); return true; }
    function update_record($t, $o) { $o = (array) $o; $id = $o['id']; unset($o['id']); $sets = []; $p = ['id' => $id]; foreach ($o as $k => $v) { $sets[] = "$k = :u_$k"; $p["u_$k"] = $v; } $this->run("UPDATE {{$t}} SET " . implode(', ', $sets) . " WHERE id = :id", $p); return true; }
    function sql_concat(...$a) { return implode(' || ', $a); }
    function sql_like($f, $p, $cs = true, $as = true) { return "$f " . ($cs ? 'LIKE' : 'ILIKE') . " $p"; }
    function sql_like_escape($s) { return addcslashes($s, '%_\\'); }
    function sql_compare_text($f) { return $f; }
}
class fake_rs implements IteratorAggregate { function __construct(private array $r) {} function getIterator(): Iterator { return new ArrayIterator($this->r); } function close() {} }
$DB = new fake_db();
require $CFG->dirroot . '/mod/stage/classes/local/progress_cache.php';
}
namespace {
// Stockage de fichiers : compte une requête par lecture de zone, comme Moodle.
class fake_file { function __construct(public $r) {} function delete() { global $DB; $DB->pdo->exec('DELETE FROM mdl_files WHERE id = ' . (int) $this->r->id); $DB->queries++; } function get_filename() { return $this->r->filename; } }
class fake_fs {
    function get_area_files($ctx, $comp, $area, $item, $sort = '', $dirs = true) { global $DB; return array_map(fn($r) => new fake_file($r), $DB->get_records_select('files', "contextid = ? AND component = ? AND filearea = ? AND itemid = ? AND filename <> '.'", [$ctx, $comp, $area, $item])); }
    function delete_area_files($ctx, $comp, $area, $item) { foreach ($this->get_area_files($ctx, $comp, $area, $item) as $f) { $f->delete(); } return true; }
    function get_file_instance($r) { return new fake_file($r); }
}
class moodle_exception extends Exception { function __construct($k, $c = '') { parent::__construct($k); } }
function get_coursemodule_from_instance(...$a) { return false; }
function get_file_storage() { return new fake_fs(); }
class context {}
class fake_context extends context { function __construct(public $id) {} }
}
