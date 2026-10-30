# Changelog

## [2.0.0] - 2026-10-30

QueryCheck now compiles queries into closures, and no longer relies on PHP's type juggling to compare values.

Queries that compare values of the same type (numbers with numbers, strings with strings, as in `{age: {$gte: 18}}` on `30`) give the same results as in 1.x. The changed results are mostly edge cases where values of different types were compared and PHP's type juggling decided the outcome: `"admin" >= 18`, `[] >= 18` and `null < 5` were all true in 1.x and no longer are. A few changes go beyond that:

* two numeric strings now compare as text (`"10" < "9"`),
* `NaN` now equals `NaN`, as in MongoDB,
* `$ne` and `$in` look at the elements of array fields,
* some broken queries are now a `SyntaxError` instead of silently matching or not matching,
* `setUndefinedEqualsNull()` was removed.

Because results can change, this is a major release. See [Upgrading from 1.x](#upgrading-from-1x) for what to check.

### Security

* **`$gt`, `$gte`, `$lt`, `$lte` no longer use PHP's `>` and `<` across types.** In 1.x, `{age: {$gte: 18}}` matched `"admin"`, `[]` and `true`, and `{failedAttempts: {$lt: 5}}` matched `null` and `false`. Values of different types no longer match (see [Comparison Rules](README.md#comparison-rules)). This also applies inside `$expr`, which ignored strict mode in 1.x.
* **Two strings compare as text.** In 1.x, two numeric strings compared as numbers, so `{code: {$gte: "1000", $lte: "1000"}}` matched `"1e3"` and `" 1000"`, though `{code: "1000"}` did not.
* **`$ne` is the negation of `$eq` on array fields, and `$in` matches array elements.** In 1.x, `{role: {$ne: "banned"}}` matched `["banned", "admin"]`, and `{role: {$in: ["admin"]}}` did not match `["admin"]`. Allow lists and deny lists on the same field now agree.
* **Strict mode no longer mixes strings and numbers in `$in`, array elements and `$eq`/`$ne`/`$in` inside `$expr`.** In 1.x, strict `{id: 1}` matched `["1"]`.
* **A `$regex` failure no longer counts as "no match".** In 1.x, a pattern that failed to compile, an unknown option, a pattern broken by the delimiter escaping (`a\/b`, a trailing `\`), PCRE's backtrack limit and invalid UTF-8 all silently returned "no match", which `$not` turned into a match: `{v: {$not: {$regex: "("}}}` matched every document. Invalid patterns and options are now a `SyntaxError` on the first `test()`, the escaping is fixed, and a failure while matching throws the new `RegexError`.
* **A lone `$options` is a `SyntaxError`.** In 1.x, `{v: {$options: "i"}}` matched every document.
* **`$expr` operator objects must have exactly one key, and the top level of `$expr` must be an operator.** In 1.x, `{$expr: {$gte: ["$age", 18], $lt: ["$age", 65]}}` silently ignored `$lt`, and the typo `{$expr: {gte: ...}}` matched every document. Both are now a `SyntaxError`, as in MongoDB.
* **Paths with an empty segment are a `SyntaxError`.** In 1.x, `.`, `..` and `"$"` inside `$expr` resolved to the whole document.
* **`QueryCheck` instances are freed when they go out of scope.** In 1.x, every instance created 38 closures that referenced it back, so it stayed in memory until PHP's cycle collector ran (about 17 KB per instance).

### Changed

* **Comparison rules** for `$gt`, `$gte`, `$lt`, `$lte` (query and `$expr`):
  * numbers vs numbers numerically, strings vs strings as text (`"10" < "9"`), booleans vs booleans (`false < true`), `null` vs `null` as equal;
  * without strict mode, a number vs a numeric string still compares numerically (`137 > "136"`);
  * anything else does not match (in strict mode it throws, except for `null`);
  * an array field never matches them (MongoDB compares each element).
* **Integers and floats are one type everywhere**, in both modes: `{v: 12}` matches `12.0`, `{v: {$ne: 1}}` no longer matches `1.0`, and strict mode no longer throws for `12.1 > 10`.
* **Integers beyond 2^53 compare exactly with floats**: `9007199254740993` no longer equals `9007199254740992.0`.
* **`NaN` equals `NaN`** for `$eq`, `$ne`, `$in`, `$gte` and `$lte`, and is never greater or less than a number, as in MongoDB.
* **`{v: null}` matches `[null]`**, so `$eq` and `$ne` stay each other's negation on arrays.
* **`$or` short-circuits**: it stops at the first matching branch, like `$and`. Later branches are not evaluated, cannot throw, and the operand evaluator is not called for them.
* **Syntax errors are reported for the whole query** on the first `test()`, also in branches that would not be evaluated for that document.
* **A non-string query key** (for example a list as the query) throws `SyntaxError` instead of a PHP `TypeError`.
* **More mistakes are a `SyntaxError`** instead of silently changing the result (see [Compiled Queries](README.md#compiled-queries)): an unknown operator like `{age: {$gtt: 18}}` (1.x compared it as a literal object), an object that mixes fields and operators, `$in` with an operand that is not a list, `$not` with a value instead of an operator expression, empty `$and`/`$or`, `$gt`/`$gte`/`$lt`/`$lte` against an array or object literal, and `$divide`/`$mod` by a literal `0`. With an operand evaluator, objects like `{$var: ...}` stay allowed as custom functions.
* **`$regex` only matches strings**, and numbers as text without strict mode. `null`, booleans, arrays and objects do not match (1.x matched arrays as the string `"Array"`); in strict mode they throw `StrictTypeError`, except `null`. The pattern must be a string, and `$options` may only contain `i`, `m`, `s`, `x` and `u`.
* **Strict mode** now means: two non-null values of different types are an error (integers and floats are one type), `null` never is.
  * `$ne` throws exactly when `$eq` would (1.x returned `true` for `{v: {$ne: 137}}` on `"137"`).
  * `$gt`, `$gte`, `$lt`, `$lte` with `null` against another type do not match and do not throw.
  * `$gt`, `$gte`, `$lt`, `$lte` throw for arrays and objects, also inside `$expr`.
  * Booleans vs booleans and `null` vs `null` can be ordered.

### Removed

* **`setUndefinedEqualsNull()`.** Use `setThrowOnUnknownVariable(false)`, which does exactly the same: a missing field compares as `null` instead of throwing. PHP has no `undefined`, so the two settings were the same switch.

### Added

* `setThrowOnUnknownVariable(bool)`: missing fields throw `UnknownVariableException` by default (as in 1.x); pass `false` to compare them as `null` instead. Replaces `setUndefinedEqualsNull(true)`.
* Compiled queries: the query is compiled into closures on the first `test()` and reused for every later call. Setters discard the compiled query.
* `validate()`: compiles the query now and throws `SyntaxError` for an invalid query, so broken rules can be found where they are loaded instead of with the first document.
* Syntax errors found while compiling start with their location, for example `$or[1].age.$not.foo: Unsupported query operator: foo`. The original error is available as `getPrevious()`.
* `Exception\RegexError`: thrown when a `$regex` fails while matching (backtrack limit, invalid UTF-8).
* The [`$literal`](https://www.mongodb.com/docs/manual/reference/operator/aggregation/literal/) aggregation operator: returns a value without parsing it. Use it for values inside `$expr` that may start with `$`, such as user input, which would otherwise become a field reference (`"$role"` would compare with the field `role`).
* README sections: Comparison Rules, Strict Mode, Missing Fields, Differences from MongoDB, Differences from the JavaScript Version.

### Fixed

* A PHP 8.5 warning ("unexpected NAN value was coerced to string") when `$eq` compared `NaN` with a string.
* `$cond` rejected a `null` `then` or `else` branch as a syntax error.
* `$regex` patterns containing an escaped slash (`a\/b`) failed to compile.

### Performance

Measured on PHP 8.5 with opcache, against 1.5.1 (numbers vary a little between runs):

| Query | First `test()` incl. build | Each later `test()` |
|---|---|---|
| 1 condition | 9.3× faster | 6.1× faster |
| Medium (4 fields, `$in`, `$or`) | 2.4× faster | 4.0× faster |
| Strings + `$regex` | 3.6× faster | 4.9× faster |
| `$expr` | 3.9× faster | 3.5× faster |
| 50 conditions | about 2× slower | 8.9× faster (ahead from the 3rd or 4th call) |
| A real ruleset (base condition + 3 rules) | 1.7× faster | 2.8× faster |

With the JIT, later calls were about 8–14× faster. Create one `QueryCheck` per query and reuse it. The first-call numbers were measured before the compile-time checks were added; those make building about 10% slower, which only affects the first call. Error locations cost nothing while compiling: only after a syntax error is the query compiled a second time to find the location.

### Tests

* 3,957 tests (up from 169), including a cross-check of the specialized closures against the generic implementations over 3,360 value/operator/operand combinations, and checks of every README example.
* Results were compared with MongoDB 8.3.13: integer vs float, `NaN`, `null`, booleans, strings and `$eq`/`$ne`/`$in` on arrays agree. The remaining differences are listed in [Differences from MongoDB](README.md#differences-from-mongodb).

### Upgrading from 1.x

Replace `setUndefinedEqualsNull(true)` with `setThrowOnUnknownVariable(false)`; calls to `setUndefinedEqualsNull()` now fail with an `Error` (undefined method).

Check queries that rely on any of these:

* **Mixed types in `$gt`, `$gte`, `$lt`, `$lte`.** Strings, arrays, `null` or booleans that used to pass a numeric bound no longer do. Convert values to numbers before calling `test()` if they arrive as strings with units or currencies (`"EUR 1500"` no longer counts as `>= 1000`).
* **Numeric strings compared with numeric strings.** `{v: {$gt: "9"}}` no longer matches `"10"`. Compare with a number (`{v: {$gt: 9}}`) to order numerically.
* **`$ne` and `$in` on array fields** now look at the elements.
* **`$or` with later branches that throw** (for example a missing field) now returns `true` if an earlier branch matched.
* **Strict mode:** `$ne` now throws for a string vs a number; `null` no longer throws in ordering; integer vs float no longer throws; `$expr` ordering now follows strict mode.
* **Syntax errors** in unreached branches are now reported, and more mistakes are syntax errors (unknown operators like `$gtt`, a lone `$options`, invalid `$regex` patterns and options, empty `$and`/`$or`, `$in` with a non-list, `$not` with a value, empty path segments, `$expr` objects with more than one key). Call `validate()` where rules are loaded to find them early.
* **`$regex` on non-strings**: arrays, booleans and objects no longer match; a pattern must be a string (`{$regex: 12}` is a syntax error); matching failures throw `RegexError`.

## [1.5.1] and earlier

See the git history.
