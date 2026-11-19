<?php

namespace App\Support\Legacy;

use InvalidArgumentException;

class SqlDumpReader
{
    /**
     * Read INSERT rows from a phpMyAdmin SQL dump without connecting to a database.
     *
     * @return array<string, list<array<string, string|null>>>
     */
    public function read(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read SQL dump [{$path}].");
        }

        $rows = [];

        preg_match_all(
            '/INSERT\s+INTO\s+`([^`]+)`\s*\(([^)]*)\)\s*VALUES\s*(.*?);/is',
            $contents,
            $statements,
            PREG_SET_ORDER,
        );

        foreach ($statements as $statement) {
            $table = $statement[1];
            $columns = array_map(
                fn (string $column): string => trim(trim($column), '`'),
                explode(',', $statement[2]),
            );

            foreach ($this->tuples($statement[3]) as $tuple) {
                $values = $this->values($tuple);

                if (count($values) !== count($columns)) {
                    throw new InvalidArgumentException(
                        "Column/value mismatch in INSERT for [{$table}].",
                    );
                }

                $rows[$table][] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Split the VALUES clause into parenthesised tuples.
     *
     * @return list<string>
     */
    private function tuples(string $sql): array
    {
        $tuples = [];
        $depth = 0;
        $start = 0;
        $quoted = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === '\\' && $quoted) {
                $i++;

                continue;
            }

            if ($char === "'") {
                if ($quoted && $i + 1 < $length && $sql[$i + 1] === "'") {
                    $i++;

                    continue;
                }

                $quoted = ! $quoted;

                continue;
            }

            if ($quoted) {
                continue;
            }

            if ($char === '(') {
                if ($depth === 0) {
                    $start = $i + 1;
                }

                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $tuples[] = substr($sql, $start, $i - $start);
                }
            }
        }

        return $tuples;
    }

    /**
     * Split one tuple into SQL values.
     *
     * @return list<string|null>
     */
    private function values(string $tuple): array
    {
        $values = [];
        $current = '';
        $quoted = false;
        $length = strlen($tuple);

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

            if ($char === '\\' && $quoted && $i + 1 < $length) {
                $current .= $char.$tuple[$i + 1];
                $i++;

                continue;
            }

            if ($char === "'") {
                if ($quoted && $i + 1 < $length && $tuple[$i + 1] === "'") {
                    $current .= "''";
                    $i++;

                    continue;
                }

                $quoted = ! $quoted;
                $current .= $char;

                continue;
            }

            if ($char === ',' && ! $quoted) {
                $values[] = $this->value(trim($current));
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $values[] = $this->value(trim($current));

        return $values;
    }

    private function value(string $raw): ?string
    {
        if ($raw === 'NULL') {
            return null;
        }

        if (strlen($raw) >= 2 && str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            $inner = substr($raw, 1, -1);

            return str_replace(
                ['\\r\\n', '\\n', '\\r', "''", "\\'"],
                ["\n", "\n", "\n", "'", "'"],
                $inner,
            );
        }

        return $raw;
    }
}
