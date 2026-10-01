<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Restricted arithmetic for signage recipes.
 *
 * It tokenises a formula and walks it. It does not call eval(), include
 * files, or pass the text to MySQL. Only listed functions and the variables
 * supplied by the caller are allowed.
 */
final class FormulaService
{
    /** @var list<string> */
    public const FUNCTIONS = ['CEIL', 'FLOOR', 'ROUND', 'MAX', 'MIN', 'ABS'];

    private string $src = '';

    private int $i = 0;

    /** @var array<string, string> */
    private array $vars = [];

    /**
     * @param array<string, string|int|float> $variables
     */
    public function evaluate(string $formula, array $variables): string
    {
        $formula = trim($formula);
        if ($formula === '') {
            throw new FormulaRejected('Enter a formula.');
        }
        if (preg_match('/[^0-9A-Za-z_+\\-*\\/().,\\s]/', $formula) === 1) {
            throw new FormulaRejected('That formula uses a character that is not allowed.');
        }
        $upper = strtoupper($formula);
        foreach (['PHP', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'EXEC', 'SYSTEM', 'SHELL', 'EVAL', 'INCLUDE', 'REQUIRE', 'UNION', 'SLEEP', 'BENCHMARK'] as $word) {
            if (preg_match('/\\b' . $word . '\\b/', $upper) === 1) {
                throw new FormulaRejected('That formula is not allowed.');
            }
        }

        $this->src = $upper;
        $this->i = 0;
        $this->vars = [];
        foreach ($variables as $name => $value) {
            $key = strtoupper((string) $name);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new FormulaRejected('Variable names must be letters, numbers, and underscores.');
            }
            $text = str_replace(',', '.', trim((string) $value));
            if (!Decimal::isNumeric($text)) {
                throw new FormulaRejected('Variable ' . $key . ' is not a number.');
            }
            $this->vars[$key] = $text;
        }

        $value = $this->parseExpr();
        $this->skip();
        if ($this->i < strlen($this->src)) {
            throw new FormulaRejected('The formula could not be read completely.');
        }

        return $value;
    }

    /**
     * @param array<string, string|int|float> $variables
     */
    public function isValid(string $formula, array $variables): bool
    {
        try {
            $this->evaluate($formula, $variables);

            return true;
        } catch (FormulaRejected) {
            return false;
        }
    }

    private function parseExpr(): string
    {
        $left = $this->parseTerm();
        while (true) {
            $this->skip();
            $op = $this->peek();
            if ($op !== '+' && $op !== '-') {
                break;
            }
            $this->i++;
            $right = $this->parseTerm();
            $left = $op === '+'
                ? Decimal::add($left, $right, Decimal::CALC_SCALE)
                : Decimal::sub($left, $right, Decimal::CALC_SCALE);
        }

        return $left;
    }

    private function parseTerm(): string
    {
        $left = $this->parseUnary();
        while (true) {
            $this->skip();
            $op = $this->peek();
            if ($op !== '*' && $op !== '/') {
                break;
            }
            $this->i++;
            $right = $this->parseUnary();
            if ($op === '/') {
                if (Decimal::cmp($right, '0') === 0) {
                    throw new FormulaRejected('A formula cannot divide by zero.');
                }
                $left = Decimal::div($left, $right, Decimal::CALC_SCALE);
            } else {
                $left = Decimal::mul($left, $right, Decimal::CALC_SCALE);
            }
        }

        return $left;
    }

    private function parseUnary(): string
    {
        $this->skip();
        if ($this->peek() === '-') {
            $this->i++;

            return Decimal::sub('0', $this->parseUnary(), Decimal::CALC_SCALE);
        }
        if ($this->peek() === '+') {
            $this->i++;

            return $this->parseUnary();
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): string
    {
        $this->skip();
        $ch = $this->peek();
        if ($ch === '(') {
            $this->i++;
            $value = $this->parseExpr();
            $this->skip();
            if ($this->peek() !== ')') {
                throw new FormulaRejected('A closing bracket is missing.');
            }
            $this->i++;

            return $value;
        }
        if ($ch !== '' && ctype_digit($ch)) {
            return $this->number();
        }
        if ($ch !== '' && ctype_alpha($ch)) {
            $name = $this->identifier();
            $this->skip();
            if ($this->peek() === '(') {
                return $this->call($name);
            }
            if (!array_key_exists($name, $this->vars)) {
                throw new FormulaRejected('Unknown value ' . $name . '.');
            }

            return $this->vars[$name];
        }

        throw new FormulaRejected('The formula could not be read.');
    }

    private function call(string $name): string
    {
        if (!in_array($name, self::FUNCTIONS, true)) {
            throw new FormulaRejected('The function ' . $name . ' is not allowed.');
        }
        $this->i++;
        $args = [];
        $this->skip();
        if ($this->peek() !== ')') {
            while (true) {
                $args[] = $this->parseExpr();
                $this->skip();
                if ($this->peek() === ',') {
                    $this->i++;
                    continue;
                }
                break;
            }
        }
        if ($this->peek() !== ')') {
            throw new FormulaRejected('A function is missing its closing bracket.');
        }
        $this->i++;

        return match ($name) {
            'ABS' => $this->need(1, $args, $name) && Decimal::cmp($args[0], '0') < 0
                ? Decimal::sub('0', $args[0], Decimal::CALC_SCALE)
                : $args[0],
            'CEIL' => $this->need(1, $args, $name) ? $this->ceil($args[0]) : '0',
            'FLOOR' => $this->need(1, $args, $name) ? $this->floor($args[0]) : '0',
            'ROUND' => $this->roundCall($args),
            'MAX' => $this->extreme($args, true),
            'MIN' => $this->extreme($args, false),
            default => throw new FormulaRejected('The function ' . $name . ' is not allowed.'),
        };
    }

    /**
     * @param list<string> $args
     */
    private function need(int $count, array $args, string $name): bool
    {
        if (count($args) !== $count) {
            throw new FormulaRejected($name . ' needs ' . $count . ' value' . ($count === 1 ? '' : 's') . '.');
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function roundCall(array $args): string
    {
        if (count($args) < 1 || count($args) > 2) {
            throw new FormulaRejected('ROUND needs one or two values.');
        }
        $scale = 0;
        if (isset($args[1])) {
            if (Decimal::cmp($args[1], '0') < 0 || Decimal::cmp($args[1], '6') > 0) {
                throw new FormulaRejected('ROUND can keep up to 6 decimal places.');
            }
            $scale = (int) $this->floor($args[1]);
        }

        return Decimal::round($args[0], $scale);
    }

    /**
     * @param list<string> $args
     */
    private function extreme(array $args, bool $max): string
    {
        if ($args === []) {
            throw new FormulaRejected(($max ? 'MAX' : 'MIN') . ' needs a value.');
        }
        $best = $args[0];
        foreach ($args as $arg) {
            $cmp = Decimal::cmp($arg, $best);
            if ($max ? $cmp > 0 : $cmp < 0) {
                $best = $arg;
            }
        }

        return $best;
    }

    public function ceil(string $value): string
    {
        [$int, $frac, $negative] = $this->parts($value);
        if ($frac === '' || preg_match('/^0+$/', $frac) === 1) {
            return $negative ? '-' . $int : $int;
        }

        return $negative ? '-' . $int : Decimal::add($int, '1', 0);
    }

    public function floor(string $value): string
    {
        [$int, $frac, $negative] = $this->parts($value);
        if ($frac === '' || preg_match('/^0+$/', $frac) === 1) {
            return $negative ? '-' . $int : $int;
        }

        return $negative ? '-' . Decimal::add($int, '1', 0) : $int;
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function parts(string $value): array
    {
        $normal = Decimal::round($value, Decimal::CALC_SCALE);
        $negative = str_starts_with($normal, '-');
        $plain = ltrim($normal, '-');
        $bits = explode('.', $plain, 2);

        return [$bits[0] === '' ? '0' : $bits[0], $bits[1] ?? '', $negative];
    }

    private function number(): string
    {
        $start = $this->i;
        while ($this->i < strlen($this->src) && (ctype_digit($this->src[$this->i]) || $this->src[$this->i] === '.')) {
            $this->i++;
        }
        $text = substr($this->src, $start, $this->i - $start);
        if (!Decimal::isNumeric($text)) {
            throw new FormulaRejected('A number in the formula is not valid.');
        }

        return $text;
    }

    private function identifier(): string
    {
        $start = $this->i;
        while ($this->i < strlen($this->src) && (ctype_alnum($this->src[$this->i]) || $this->src[$this->i] === '_')) {
            $this->i++;
        }

        return substr($this->src, $start, $this->i - $start);
    }

    private function skip(): void
    {
        while ($this->i < strlen($this->src) && ctype_space($this->src[$this->i])) {
            $this->i++;
        }
    }

    private function peek(): string
    {
        return $this->i < strlen($this->src) ? $this->src[$this->i] : '';
    }
}
