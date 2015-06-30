<?php
namespace Strata\Model\CustomPostType;

use Strata\Strata;
use WP_Query;

class Query
{
    // Set defaults: return a list of published posts ordered by name
    protected $_filters = array(
        'orderby'          => 'title',
        'order'            => 'ASC',
        'post_status'      => 'any',
        'nopaging'         => true,
        'suppress_filters' => true,
    );

    private $executionStart = 0;

    public function fetch()
    {
        $query = $this->query();
        return $query->posts;
    }

    public function query()
    {
        $this->logQueryStart();
        $result = new WP_Query($this->_filters);
        $this->logQueryCompletion($result->request);
        return $result;
    }

    public function listing($key, $label)
    {
        $data = array();
        foreach ($this->fetch() as $entity) {
            $data[$entity->{$key}] = $entity->{$label};
        }
        return $data;
    }

    public function date($dateQuery)
    {
        $this->_filters['date_query'] = $dateQuery;
        return $this;
    }

    public function orderby($orderBy)
    {
        $this->_filters['orderby'] = $orderBy;
        return $this;
    }

    public function direction($order)
    {
        $this->_filters['order'] = $order;
        return $this;
    }

    public function type($type = null)
    {
        if (is_null($type)) {
            unset($this->_filters['post_type']);
        } else {
            $this->_filters['post_type'] = $type;
        }
        return $this;
    }

    public function status($status = null)
    {
        if (is_null($status)) {
            unset($this->_filters['post_status']);
        } else {
            $this->_filters['post_status'] = $status;
        }

        return $this;
    }

    public function where($field, $value)
    {
        $this->_filters[$field]   = $value;
        return $this;
    }

    public function limit($qty)
    {
        $this->_filters['posts_per_page']   = $qty;
        $this->_filters['nopaging']         = false;
        return $this;
    }

    private function logQueryStart()
    {
        $app = Strata::app();
        $this->executionStart = microtime(true);
    }

    private function logQueryCompletion($sql)
    {
        $app = Strata::app();
        $executionTime = microtime(true) - $this->executionStart;
        $timer = sprintf(" (Done in %s seconds)", round($executionTime, 4));

        $oneLine = preg_replace('/\s+/', ' ', trim($sql));
        $app = Strata::app();
        $app->log($oneLine . $timer, "[Strata:Query]");
    }

}
