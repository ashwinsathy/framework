<?php
/**
 * Paginator - Implements a simple but efficient Paginator.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Pagination;

use Pagination\Presenter;
use Support\Collection;
use Support\Contracts\JsonableInterface;
use Support\Contracts\ArrayableInterface;

use Input;
use Request;

use Countable;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;


class Paginator implements ArrayableInterface, ArrayAccess, Countable, IteratorAggregate, JsonableInterface
{
    /**
     * The results for the current page.
     *
     * @var array
     */
    protected $items;


    /**
     * The total number of results.
     *
     * @var int
     */
    protected $total;

    /**
     * The number of items per page.
     *
     * @var int
     */
    protected $perPage;

    /**
     * Get the current page for the request.
     *
     * @var int
     */
    protected $currentPage;

    /**
     * Get the last available page number.
     *
     * @return int
     */
    protected $lastPage;

    /**
     * The number of the first item in this range.
     *
     * @var int
     */
    protected $from;

    /**
     * The number of the last item in this range.
     *
     * @var int
     */
    protected $to;

    /**
     * The base URL in use by the paginator.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * The input parameter used for the current page.
     *
     * @var string
     */
    protected $pageName;

    /**
     * All of the additional query string values.
     *
     * @var array
     */
    protected $query = array();

    /**
     * The Presenter instance.
     *
     * @var \Pagination\Presenter
     */
    protected $presenter;


    /**
     * Create a new Paginator instance.
     *
     * @param  array  $items
     * @param  int    $page
     * @param  int    $total
     * @param  int    $perPage
     * @param  int    $last
     * @return void
     */
    protected function __construct($items, $page, $total, $perPage, $lastPage, $pageName)
    {
        $this->items       = $items;
        $this->currentPage = $page;
        $this->total       = $total;
        $this->perPage     = $perPage;
        $this->lastPage    = $lastPage;
        $this->pageName    = $pageName;

        // Calculate the Item ranges.
        $this->calculateItemRanges();
    }

    /**
     * Create a new Paginator instance.
     *
     * @param  array      $results
     * @param  int        $total
     * @param  int        $perPage
     * @return Paginator
     */
    public static function make($results, $total, $perPage, $pageName = 'offset')
    {
        $page = static::page($total, $perPage, $pageName);

        $lastPage = ceil($total / $perPage);

        return new static($results, $page, $total, $perPage, $lastPage, $pageName);
    }

    /**
     * Get the current page from the request query string.
     *
     * @param  int  $total
     * @param  int  $perPage
     * @return int
     */
    public static function page($total, $perPage, $pageName = 'offset')
    {
        $page = Input::get($pageName, 1);

        // Validate and adjust page if it is less than one or greater than the last page.
        if (is_numeric($page) && ($page > ($last = ceil($total / $perPage)))) {
            return ($last > 0) ? $last : 1;
        }

        return static::isValidPageNumber($page) ? $page : 1;
    }

    /**
     * Determine if a given page number is a valid page.
     *
     * A valid page must be greater than or equal to one and a valid integer.
     *
     * @param  int   $page
     * @return bool
     */
    protected static function isValidPageNumber($page)
    {
        return (($page >= 1) && (filter_var($page, FILTER_VALIDATE_INT) !== false));
    }

    /**
     * Calculate the first and last item number for this instance.
     *
     * @return void
     */
    protected function calculateItemRanges()
    {
        $this->from = $this->total ? ($this->currentPage - 1) * $this->perPage + 1 : 0;

        $this->to = min($this->total, $this->currentPage * $this->perPage);
    }

    /**
     * Create the HTML pagination links.
     *
     * @param  int  $adjacent
     * @return string
     */
    public function links($adjacent = 3)
    {
        $presenter = $this->getPresenter();

        return $presenter->render();
    }

    /**
     * Get a URL for a given page number.
     *
     * @param  int     $page
     * @return string
     */
    public function getUrl($page)
    {
        $params = array(
            $this->pageName => $page,
        );

        if (count($this->query) > 0) {
            $params = array_merge($this->query, $params);
        }

        return $this->getCurrentUrl() .'?' .http_build_query($params, null, '&');
    }

    /**
     * Add a query string value to the paginator.
     *
     * @param  string  $key
     * @param  string  $value
     * @return \Pagination\Paginator
     */
    public function appends($key, $value = null)
    {
        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * Add an array of query string values.
     *
     * @param  array  $keys
     * @return \Pagination\Paginator
     */
    protected function appendArray(array $keys)
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * Add a query string value to the paginator.
     *
     * @param  string  $key
     * @param  string  $value
     * @return \Pagination\Paginator
     */
    public function addQuery($key, $value)
    {
        $this->query[$key] = $value;

        return $this;
    }

    /**
     * Set the base URL in use by the paginator.
     *
     * @param  string  $baseUrl
     * @return void
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get the root URL for the request.
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        return $this->baseUrl ?: Request::url();
    }

    /**
     * Set the input page parameter name used by the paginator.
     *
     * @param  string  $pageName
     * @return void
     */
    public function setPageName($pageName)
    {
        $this->pageName = $pageName;
    }

    /**
     * Get the input page parameter name used by the paginator.
     *
     * @return string
     */
    public function getPageName()
    {
        return $this->pageName;
    }

    /**
     * Get the number of the current page.
     *
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Get the last page that should be available.
     *
     * @return int
     */
    public function getLastPage()
    {
        return $this->lastPage;
    }

    /**
     * Get the number of the first item on the paginator.
     *
     * @return int
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Get the number of the last item on the paginator.
     *
     * @return int
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Get the number of Items to be displayed per page.
     *
     * @return int
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * Get a collection instance containing the items.
     *
     * @return \Support\Collection
     */
    public function getCollection()
    {
        return new Collection($this->items);
    }

    /**
     * Get the items being paginated.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the items being paginated.
     *
     * @param  mixed  $items
     * @return void
     */
    public function setItems($items)
    {
        $this->items = $items;
    }

    /**
     * Get the total number of items in the collection.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get a Presenter instance.
     *
     * @return \Pagination\Presenter
     */
    public function getPresenter()
    {
        if (isset($this->presenter)) {
            return $this->presenter;
        }

        return $this->presenter = new Presenter($this);
    }

    /**
     * Set the Presenter instance.
     *
     * @param \Pagination\Presenter $presenter
     * @return \Pagination\Paginator
     */
    public function setPresenter(Presenter $presenter)
    {
        $this->presenter = $presenter;

        return $this;
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Determine if the list of items is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Get the number of items for the current page.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Determine if the given item exists.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get the item at the given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at the given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * Unset the item at the given key.
     *
     * @param  mixed  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'from'         => $this->from,
            'to'           => $this->to,
            'data'         => $this->getCollection()->toArray(),
        );
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Call a method on the underlying Collection
     *
     * @param  string  $method
     * @param  array   $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $collection = $this->getCollection();

        return call_user_func_array(array($collection, $method), $arguments);
    }
}
