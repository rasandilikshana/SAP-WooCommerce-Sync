<?php
/**
 * SAP OData Request Builder class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/SAP
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\SAP;

/**
 * Builds OData query parameters for SAP API requests.
 *
 * Provides fluent interface for building $filter, $select, $expand,
 * $orderby, $top, $skip, and $count parameters.
 *
 * @since 1.0.0
 */
class Request_Builder
{

    /**
     * Fields to select.
     *
     * @since 1.0.0
     * @var array<string>
     */
    private array $select = [];

    /**
     * Filter conditions.
     *
     * @since 1.0.0
     * @var array<string>
     */
    private array $filter = [];

    /**
     * Entities to expand.
     *
     * @since 1.0.0
     * @var array<string>
     */
    private array $expand = [];

    /**
     * Order by clauses.
     *
     * @since 1.0.0
     * @var array<string>
     */
    private array $order_by = [];

    /**
     * Number of records to retrieve.
     *
     * @since 1.0.0
     * @var int|null
     */
    private ?int $top = null;

    /**
     * Number of records to skip.
     *
     * @since 1.0.0
     * @var int|null
     */
    private ?int $skip = null;

    /**
     * Whether to include count.
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $count = false;

    /**
     * Create a new request builder instance.
     *
     * @since 1.0.0
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Select specific fields.
     *
     * @since 1.0.0
     * @param string|array<string> $fields Fields to select.
     * @return static
     */
    public function select(string|array $fields): static
    {
        $fields = is_array($fields) ? $fields : [$fields];
        $this->select = array_merge($this->select, $fields);
        return $this;
    }

    /**
     * Add a filter condition.
     *
     * @since 1.0.0
     * @param string $field    Field name.
     * @param string $operator Comparison operator.
     * @param mixed  $value    Value to compare.
     * @return static
     */
    public function where(string $field, string $operator, mixed $value): static
    {
        $this->filter[] = $this->build_condition($field, $operator, $value);
        return $this;
    }

    /**
     * Add an equality filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to match.
     * @return static
     */
    public function where_equals(string $field, mixed $value): static
    {
        return $this->where($field, 'eq', $value);
    }

    /**
     * Add a "not equals" filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to exclude.
     * @return static
     */
    public function where_not_equals(string $field, mixed $value): static
    {
        return $this->where($field, 'ne', $value);
    }

    /**
     * Add a "greater than" filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to compare.
     * @return static
     */
    public function where_greater_than(string $field, mixed $value): static
    {
        return $this->where($field, 'gt', $value);
    }

    /**
     * Add a "greater than or equal" filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to compare.
     * @return static
     */
    public function where_greater_or_equal(string $field, mixed $value): static
    {
        return $this->where($field, 'ge', $value);
    }

    /**
     * Add a "less than" filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to compare.
     * @return static
     */
    public function where_less_than(string $field, mixed $value): static
    {
        return $this->where($field, 'lt', $value);
    }

    /**
     * Add a "less than or equal" filter.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Value to compare.
     * @return static
     */
    public function where_less_or_equal(string $field, mixed $value): static
    {
        return $this->where($field, 'le', $value);
    }

    /**
     * Add a "contains" filter.
     *
     * @since 1.0.0
     * @param string $field  Field name.
     * @param string $search Search string.
     * @return static
     */
    public function where_contains(string $field, string $search): static
    {
        $this->filter[] = sprintf("contains(%s, '%s')", $field, $this->escape_string($search));
        return $this;
    }

    /**
     * Add a "starts with" filter.
     *
     * @since 1.0.0
     * @param string $field  Field name.
     * @param string $search Search string.
     * @return static
     */
    public function where_starts_with(string $field, string $search): static
    {
        $this->filter[] = sprintf("startswith(%s, '%s')", $field, $this->escape_string($search));
        return $this;
    }

    /**
     * Add a "ends with" filter.
     *
     * @since 1.0.0
     * @param string $field  Field name.
     * @param string $search Search string.
     * @return static
     */
    public function where_ends_with(string $field, string $search): static
    {
        $this->filter[] = sprintf("endswith(%s, '%s')", $field, $this->escape_string($search));
        return $this;
    }

    /**
     * Add an "in" filter for multiple values.
     *
     * @since 1.0.0
     * @param string       $field  Field name.
     * @param array<mixed> $values Values to match.
     * @return static
     */
    public function where_in(string $field, array $values): static
    {
        $conditions = array_map(
            fn($value) => $this->build_condition($field, 'eq', $value),
            $values
        );
        $this->filter[] = '(' . implode(' or ', $conditions) . ')';
        return $this;
    }

    /**
     * Add a raw filter condition.
     *
     * @since 1.0.0
     * @param string $condition Raw OData filter condition.
     * @return static
     */
    public function where_raw(string $condition): static
    {
        $this->filter[] = $condition;
        return $this;
    }

    /**
     * Expand related entities.
     *
     * @since 1.0.0
     * @param string|array<string> $entities Entities to expand.
     * @return static
     */
    public function expand(string|array $entities): static
    {
        $entities = is_array($entities) ? $entities : [$entities];
        $this->expand = array_merge($this->expand, $entities);
        return $this;
    }

    /**
     * Order by field ascending.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @return static
     */
    public function order_by(string $field): static
    {
        $this->order_by[] = $field . ' asc';
        return $this;
    }

    /**
     * Order by field descending.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @return static
     */
    public function order_by_desc(string $field): static
    {
        $this->order_by[] = $field . ' desc';
        return $this;
    }

    /**
     * Limit the number of results.
     *
     * @since 1.0.0
     * @param int $count Number of records.
     * @return static
     */
    public function top(int $count): static
    {
        $this->top = $count;
        return $this;
    }

    /**
     * Alias for top().
     *
     * @since 1.0.0
     * @param int $count Number of records.
     * @return static
     */
    public function limit(int $count): static
    {
        return $this->top($count);
    }

    /**
     * Skip a number of results.
     *
     * @since 1.0.0
     * @param int $count Number of records to skip.
     * @return static
     */
    public function skip(int $count): static
    {
        $this->skip = $count;
        return $this;
    }

    /**
     * Alias for skip().
     *
     * @since 1.0.0
     * @param int $count Number of records to skip.
     * @return static
     */
    public function offset(int $count): static
    {
        return $this->skip($count);
    }

    /**
     * Paginate results.
     *
     * @since 1.0.0
     * @param int $page     Page number (1-indexed).
     * @param int $per_page Items per page.
     * @return static
     */
    public function paginate(int $page, int $per_page = 20): static
    {
        $this->top = $per_page;
        $this->skip = (max(1, $page) - 1) * $per_page;
        return $this;
    }

    /**
     * Include total count in response.
     *
     * @since 1.0.0
     * @param bool $include Whether to include count.
     * @return static
     */
    public function with_count(bool $include = true): static
    {
        $this->count = $include;
        return $this;
    }

    /**
     * Build the query parameters array.
     *
     * @since 1.0.0
     * @return array<string, string> Query parameters.
     */
    public function build(): array
    {
        $params = [];

        if (!empty($this->select)) {
            $params['$select'] = implode(',', array_unique($this->select));
        }

        if (!empty($this->filter)) {
            $params['$filter'] = implode(' and ', $this->filter);
        }

        if (!empty($this->expand)) {
            $params['$expand'] = implode(',', array_unique($this->expand));
        }

        if (!empty($this->order_by)) {
            $params['$orderby'] = implode(',', $this->order_by);
        }

        if (null !== $this->top) {
            $params['$top'] = (string) $this->top;
        }

        if (null !== $this->skip) {
            $params['$skip'] = (string) $this->skip;
        }

        if ($this->count) {
            $params['$count'] = 'true';
        }

        return $params;
    }

    /**
     * Build a filter condition.
     *
     * @since 1.0.0
     * @param string $field    Field name.
     * @param string $operator Comparison operator.
     * @param mixed  $value    Value to compare.
     * @return string Filter condition.
     */
    private function build_condition(string $field, string $operator, mixed $value): string
    {
        $formatted_value = $this->format_value($value);
        return sprintf('%s %s %s', $field, $operator, $formatted_value);
    }

    /**
     * Format a value for OData.
     *
     * @since 1.0.0
     * @param mixed $value The value to format.
     * @return string Formatted value.
     */
    private function format_value(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return "'" . $value->format('Y-m-d') . "'";
        }

        // String value.
        return "'" . $this->escape_string((string) $value) . "'";
    }

    /**
     * Escape a string for OData.
     *
     * @since 1.0.0
     * @param string $value String to escape.
     * @return string Escaped string.
     */
    private function escape_string(string $value): string
    {
        // Escape single quotes by doubling them.
        return str_replace("'", "''", $value);
    }

    /**
     * Reset the builder.
     *
     * @since 1.0.0
     * @return static
     */
    public function reset(): static
    {
        $this->select = [];
        $this->filter = [];
        $this->expand = [];
        $this->order_by = [];
        $this->top = null;
        $this->skip = null;
        $this->count = false;
        return $this;
    }
}
