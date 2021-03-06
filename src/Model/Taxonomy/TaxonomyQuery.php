<?php

namespace Strata\Model\Taxonomy;

use Strata\Model\CustomPostType\Query;
use Strata\Logger\Debugger;
use Exception;

/**
 * The query is a cache of filters that will be eventually
 * sent to get_terms. It allows chaining and object-oriented
 * manipulations of database queries.
 */
class TaxonomyQuery extends Query
{
    /**
     * @var array A list of get_terms() or get_the_terms() filters.
     */
    protected $filters = array();

    /**
     * @var string A list of the taxonomies being queried.
     */
    protected $taxnomony = null;

    /**
     * @var int A post ID against which the terms are queried.
     */
    protected $postId = null;

    /**
     * @var array
     */
    protected $termLookupMode = null;

    /**
     * Sets the taxonomy type
     * @param  string $type
     * @return TaxonomyQuery
     */
    public function type($type = null)
    {
        $this->taxnomony = $type;
        return $this;
    }

    /**
     * Specified the query to lookup post terms and not general taxonomies.
     * @param  int $postId
     * @return TaxonomyQuery
     */
    public function againstPostId($postId)
    {
        $this->postId = (int)$postId;
        return $this;
    }

    public function triggerLookupMode($byTerm, $value)
    {
        $this->termLookupMode = array($byTerm, $value);
        return $this;
    }

    /**
     * Fetches the terms matching the TaxonomyQuery.
     * @return array
     */
    public function fetch()
    {
        $this->logQueryStart();
        $results = null;

        if (is_array($this->termLookupMode)) {
            $queryLog = "get_term_by(" . Debugger::export($this->termLookupMode[0]) . ", " . Debugger::export($this->termLookupMode[1]) . ", " . Debugger::export($this->taxnomony) . ")";
            $results = get_term_by($this->termLookupMode[0], $this->termLookupMode[1], $this->taxnomony);
        } elseif (is_null($this->postId)) {
            $queryLog = "get_terms(" . Debugger::export($this->taxnomony) . ", " . Debugger::export($this->filters) . ")";
            $results = get_terms($this->taxnomony, $this->filters);
        } else {
            $queryLog = "get_the_terms(" . Debugger::export($this->postId) . ", " . Debugger::export($this->taxnomony) . ", " . Debugger::export($this->filters) . ")";
            $results = get_the_terms($this->postId, $this->taxnomony, $this->filters);
        }

        $this->logQueryCompletion($queryLog);

        if ($results === false) {
            return array();
        }

        if (!is_array($results)) {
            $results = array($results);
        }

        return $results;
    }

}
