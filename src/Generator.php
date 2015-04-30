<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Router;

/**
 *
 * Generates URL paths from routes.
 *
 * @package Aura.Router
 *
 */
class Generator
{
    /**
     *
     * The map of all routes.
     *
     * @var Map
     *
     */
    protected $map;

    /**
     *
     * The path being generated.
     *
     * @var string
     *
     */
    protected $path;

    /**
     *
     * Data being interpolated into the path.
     *
     * @var array
     *
     */
    protected $data;

    /**
     *
     * Replacement data.
     *
     * @var array
     *
     */
    protected $repl;

    /**
     *
     * Leave values raw?
     *
     * @var bool
     *
     */
    protected $raw;

    /**
     *
     * Sets the Map object.
     *
     * @param Map $map A route collection object.
     *
     */
    public function setMap(Map $map)
    {
        $this->map = $map;
    }

    /**
     *
     * Looks up a route by name, and interpolates data into it to return
     * a URI path.
     *
     * @param string $name The route name to look up.
     *
     * @param array $data The data to interpolate into the URI; data keys
     * map to attribute tokens in the path.
     *
     * @return string|false A URI path string if the route name is found, or
     * boolean false if not.
     *
     * @throws Exception\RouteNotFound
     *
     */
    public function generate($name, $data = [])
    {
        return $this->buildPath($name, $data, false);
    }

    /**
     *
     * Generate the route without url encoding.
     *
     * @param string $name The route name to look up.
     *
     * @param array $data The data to interpolate into the URI; data keys
     * map to attribute tokens in the path.
     *
     * @return string|false A URI path string if the route name is found, or
     * boolean false if not.
     *
     * @throws Exception\RouteNotFound
     *
     */
    public function generateRaw($name, $data = [])
    {
        return $this->buildPath($name, $data, true);
    }

    /**
     *
     * Gets the path for a Route.
     *
     * @param string $name The route name to look up.
     *
     * @param array $data An array of key-value pairs to interpolate into the
     * attribute tokens in the path for the Route.
     *
     * @param bool $raw Leave the data unencoded?
     *
     * @return string
     *
     */
    protected function buildPath($name, $data, $raw)
    {
        $route = $this->map->getRoute($name);

        $this->raw = $raw;
        $this->path = $route->path;
        $this->repl = [];
        $this->data = array_merge($route->defaults, $data);

        $this->buildTokenReplacements();
        $this->buildOptionalReplacements();
        $this->path = strtr($this->path, $this->repl);
        $this->buildWildcardReplacement($route->wildcard);

        return $this->path;
    }

    /**
     *
     * Builds urlencoded data for token replacements.
     *
     * @return array
     *
     */
    protected function buildTokenReplacements()
    {
        foreach ($this->data as $key => $val) {
            $this->repl["{{$key}}"] = $this->encode($val);
        }
    }

    /**
     *
     * Builds replacements for attributes in the generated path.
     *
     * @return string
     *
     */
    protected function buildOptionalReplacements()
    {
        // replacements for optional attributes, if any
        preg_match('#{/([a-z][a-zA-Z0-9_,]*)}#', $this->path, $matches);
        if (! $matches) {
            return;
        }

        // the optional attribute names in the token
        $names = explode(',', $matches[1]);

        // this is the full token to replace in the path
        $key = $matches[0];

        // build the replacement string
        $this->repl[$key] = $this->buildOptionalReplacement($names);
    }

    protected function buildOptionalReplacement($names)
    {
        $repl = '';
        foreach ($names as $name) {
            // is there data for this optional attribute?
            if (! isset($this->data[$name])) {
                // options are *sequentially* optional, so if one is
                // missing, we're done
                return $repl;
            }
            // encode the optional value
            $repl .= '/' . $this->encode($this->data[$name]);
        }
        return $repl;
    }

    /**
     *
     * Builds a wildcard replacement in the generated path.
     *
     * @return string
     *
     */
    protected function buildWildcardReplacement($wildcard)
    {
        if ($wildcard && isset($this->data[$wildcard])) {
            $this->path = rtrim($this->path, '/');
            foreach ($this->data[$wildcard] as $val) {
                $this->path .= '/' . $this->encode($val);
            }
        }
    }

    /**
     *
     * Encodes values, or leaves them raw.
     *
     * @param string $val The value to encode or leave raw.
     *
     * @return mixed
     *
     */
    protected function encode($val)
    {
        if ($this->raw) {
            return $val;
        }

        return is_scalar($val) ? rawurlencode($val) : null;
    }
}
