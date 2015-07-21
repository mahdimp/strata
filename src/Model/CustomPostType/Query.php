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

        // This field, though not used by Wordpress will
        // allow some flexibility with combining AND and OR query
        // relations.
        'strata_relations' => array(
            'meta_query' => array("AND" => array(), "OR" => array()),
            'tax_query' => array("AND" => array(), "OR" => array()),
        ),
    );

    private $executionStart = 0;

    public function fetch()
    {
        $query = $this->query();
        return $query->posts;
    }

    public function first()
    {
        $result = $this->fetch();
        return array_shift($result);
    }

    public function query()
    {
        $this->carryOverIncompatibleQueries();
        $this->relationsToQueries();
        return $this->executeFilteredQuery();
    }

    private function executeFilteredQuery()
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
        if (strtolower($field) === "meta_query") {
            return $this->metaWhere($field, $value);
        }
        elseif (strtolower($field) === "tax_query") {
            return $this->taxWhere($field, $value);
        }

        $this->_filters[$field] = $value;
        return $this;
    }

    public function orWhere($field, $value)
    {
        if (strtolower($field) === "meta_query") {
            return $this->metaWhere($field, $value, 'OR');
        }
        elseif (strtolower($field) === "tax_query") {
            return $this->taxWhere($field, $value, 'OR');
        }

        $this->_filters[$field] = $value;
        return $this;
    }


    public function limit($qty)
    {
        $this->_filters['posts_per_page']   = $qty;
        $this->_filters['nopaging']         = false;
        return $this;
    }

    public function getFilters()
    {
        return $this->_filters;
    }

    public function applyFilters($filters)
    {
        foreach($filters as $key => $value) {
            $this->_filters[$key] = $value;
        }

        return $this;
    }


    // If previous meta queries are set, they may prevent the combination of
    // both 'AND' and 'OR' relation. Carries over the exclusive fields as a list of
    // IDs to apply in a Query with a new meta_query relation type.
    protected function carryOverIncompatibleQueries()
    {
        foreach($this->_filters['strata_relations'] as $queryType => $queryDetails) {

            if ($this->hasRelationQuery($queryType, 'AND') && $this->hasRelationQuery($queryType, 'OR')) {
                $this->andRelationToPostIn($queryType);
            }
        }

        return $this;
    }

    protected function relationsToQueries()
    {
        foreach($this->_filters['strata_relations'] as $queryType => $queryDetails) {
            // At this point, there should only be exclusive AND or OR query groups
            $metaQueries = null;
            $relationTypes = array_keys($queryDetails);
            foreach ($relationTypes as $relationType)  {
                if ($this->hasRelationQuery($queryType, $relationType)) {
                    $metaQueries = $this->getRelationQuery($queryType, $relationType);
                    $this->setQueryRelation($queryType, $relationType);
                    $this->resetQueryRelation($queryType, $relationType);
                }
            }

            if (!is_null($metaQueries)) {
                foreach($metaQueries as $query) {
                    $this->addRelationQuery($queryType, $query);
                }
            }
        }

        return $this;
    }

    protected function andRelationToPostIn($query_type)
    {
        $andQuery = new Query();

        // Copy the current Query but remove the OR conditions.
        // They will be looked up as this instance goes on with
        // the process.
        $andQuery->applyFilters($this->_filters);
        $andQuery->resetQueryRelation($query_type, 'OR');

        // This forces the AND relationships to be loaded before
        // comparing the or parameters
        $andIds = $andQuery->listing("ID", "ID");
        $this->where('post__in', array_values($andIds));
        $this->resetQueryRelation($query_type, 'AND');

        return $this;
    }

    public function resetQueryRelation($which, $type)
    {
        $this->_filters['strata_relations'][$which][$type] = array();
        return $this;
    }

    public function getRelationQuery($which, $type)
    {
        return $this->_filters['strata_relations'][$which][$type];
    }

    public function hasRelationQuery($which, $type)
    {
        return count($this->getRelationQuery($which, $type)) > 0;
    }

    public function setQueryRelation($type, $which)
    {
        $this->prepareRelationFilter($type);
        $this->_filters[$type]['relation'] = $which;
        return $this;
    }

    public function prepareRelationFilter($type)
    {
        if (!array_key_exists($type, $this->_filters) || !is_array($this->_filters[$type])) {
            $this->_filters[$type] = array();
        }
        return $this;
    }

    // This does not actually set the meta_query parameter. It is
    // used to build a more complex AND/OR logical fetch.
    protected function metaWhere($field, $value, $compare = 'AND')
    {
        $this->_filters['strata_relations']['meta_query'][$compare][] = $value;
        return $this;
    }

    // This does not actually set the meta_query parameter. It is
    // used to build a more complex AND/OR logical fetch.
    protected function taxWhere($field, $value, $compare = 'AND')
    {
        $this->_filters['strata_relations']['tax_query'][$compare][] = $value;
        return $this;
    }

    // This actually set the wp_query parameter
    private function addRelationQuery($type, $value)
    {
        $this->prepareRelationFilter($type);
        $this->_filters[$type][] = $value;
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
