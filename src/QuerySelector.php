<?php

namespace Mahmodigit\HtmlQuerySelector;

use DOMDocument;
use DOMNodeList;
use DOMXPath;

class QuerySelector
{
    use XpathQueryBuilder;

    /**
     * And concatenation operator
     */
    private const AND_OPERATOR = 'and';

    /**
     * Or concatenation operator
     */
    private const OR_OPERATOR = 'or';

    /**
     * Fro example = 'div.0', 'div.1','div.2' ...
     */
    private const UNIQUE_TAG_NAME_SEPARATOR = '.';

    /**
     * @var DOMXPath
     */
    private DOMXPath $x;

    /**
     * @param string $html
     */
    public function __construct(string $html)
    {
        $this->x = $this->buildXPath($html);
    }

    /**
     * DomXPath factory
     *
     * @param string $html
     * @return DOMXPath
     */
    private function buildXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        $dom->loadHTML($html);

        return new DOMXPath($dom);
    }
}

trait XpathQueryBuilder
{
    /**
     * @var array
     */
    private array $tags;

    /**
     * @var array
     */
    private array $whereAttributes;

    /**
     * @var string
     */
    private string $currentTagName;

    /**
     * Define html tag name selection
     *
     * @param string $name Client can define '*' for match with all tags
     * @param bool $recursively When it is true, selector searching for tag with his specified conditions in
     * anywhere of his parent event nested and deep child elements. But when it is false , selector searching
     * for tag just in parent body scope and not in nested children
     * @return XpathQueryBuilder
     */
    public function tag(string $name, bool $recursively = true)
    {
        if (isset($this->tags[$name]))
            $name = $name . self::UNIQUE_TAG_NAME_SEPARATOR . count($this->tags);

        $this->currentTagName = $name;

        $this->tags[$name] = $recursively ? '//' : '/';

        return $this;
    }

    /**
     * Set '*' for define all tags selection
     *
     * @return void
     */
    public function allTags()
    {
        $tagName = '*.' . count($this->tags);

        $this->currentTagName = $tagName;

        $this->tags[$tagName] = "//";
    }

    /**
     * Define attribute condition
     *
     * @param string $name
     * @param string|null $value
     * @return XpathQueryBuilder
     */
    public function attribute(string $name, ?string $value = null)
    {
        if (!isset($this->currentTagName))
            $this->allTags();

        $this->whereAttributes[$this->currentTagName][] = [
            'con' => "@{$name}" . ($value ? "='{$value}'" : ""),
            'operator' => self::AND_OPERATOR
        ];

        return $this;
    }

    /**
     * Define attribute  condition that separate with 'or' from other conditions
     *
     * @param string $name
     * @param string|null $value
     * @return XpathQueryBuilder
     */
    public function orAttribute(string $name, ?string $value = null)
    {
        if (!isset($this->currentTagName))
            $this->allTags();

        $this->whereAttributes[$this->currentTagName][] = [
            'con' => "@{$name}" . ($value ? "='{$value}'" : ""),
            'operator' => self::OR_OPERATOR
        ];

        return $this;
    }

    /**
     * Select elements through predefined properties
     *
     * @return DOMNodeList|false
     */
    public function select(): DOMNodeList|false
    {
        return $this->x->query($this->query());
    }

    /**
     * Returns length of elements in DOM those are match with defined properties
     *
     * @return int
     */
    public function length(): int
    {
        $list = $this->select();

        return $list->length;
    }

    /**
     * Returns query context
     *
     * @return string
     */
    public function toQuery(): string
    {
        return $this->query();
    }

    /**
     * Builds query then return
     *
     * @return string
     */
    private function query(): string
    {
        $query = "";

        foreach ($this->tags as $name => $scope) {
            $query .= $scope . (str_contains($name, '*') ? "*" : explode(self::UNIQUE_TAG_NAME_SEPARATOR, $name)[0]);

            $query .= $this->tagWhereAttributeQuery($name);
        }

        $this->reset();

        return $query;
    }

    /**
     * Processes and return attribute conditions section of specified tag
     *
     * @param string $tagName
     * @return string
     */
    private function tagWhereAttributeQuery(string $tagName): string
    {
        $query = '';

        if ($conditions = $this->whereAttributes[$tagName] ?? null)
            $query .= '[';

        foreach ($conditions as $index => $condition) {
            if ($index > 0)
                $query .= " " . $condition['operator'] . " ";

            $query .= $condition['con'];

            if ($index == (count($conditions) - 1))
                $query .= ']';
        }

        return $query;
    }

    /**
     * Reset selector through unset all properties
     *
     * @return void
     */
    private function reset(): void
    {
        unset($this->tags);

        unset($this->whereAttributes);
    }
}
