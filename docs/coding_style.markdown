Coding Style
============

It's a well known fact that code is read much more often than it is written. The guidelines provided here are intended
to improve the readability of the code and make it consistent within this project. Consistency is important but code
readability has higher priority. If applying the rule would make code less readable it's okay to ignore the rule.

And don't hesitate to ask!

_Note:_ This document was written based on great [PEP8](http://www.python.org/dev/peps/pep-0008/) proposition.


Indentation
-----------

Use tab characters instead of spaces. Ideally 4 spaces per tab. Tab characters allow developers to use different
indentation sizes while still respecting the coding style rules. _Never_ use spaces instead of tab character!

Suggested maximum line length is 120 pixels. If code is getting close to this limit it means code is getting too
complex and can be changed to be much simpler.

Long lines, complex flow control statements and variable definitions should be wrapped vertically. When using a
hanging indent avoid arguments on first line. Closing brace/bracket/parenthesis in this case must be on a separate
line with one less indent level.

Good:

```php
$something = long_function_name(
					$param_first,
					call_to_another_function(),
					$next_param
				);
```

Bad:

```php
$something = long_function_name($param_first,
call_to_another_function(), $next_param);
```

Also bad:

```php
$something = long_function_name($param_first,
		call_to_another_function(), $next_param);
```

If a function definition is too long, break extra parameters vertically and approximately align to the first parameter
of the definition. However whenever possible, avoid breaking function definitions.

Good:

```php
function long_funciton_name($parameter_with_long_name, $another_parameter, $short_one,
							$something_even_longer, $additional_parameter) {
	// function code...
}
```

Bad:

```php
function long_function_name(
	$parameter_with_long_name,
	$another_parameter,
	$short_one,
	$something_even_longer,
	$additional_parameter
) {
	// function code...
}
```


Blank Lines
-----------

When defining classes, two blank lines before and after class code is required. This makes files with more than one
class easier to read and visually search. Functions inside of class are separated with one blank line. Extra blank
lines can be used sparingly to group code inside of functions or to group imports.

Good:

```php
namespace Test;


abstract class NewClass {
	abstract public function test();
}


class AnotherClass(NewClass) {
	public function test() {
	}
}
```

Bad:

```php
namespace Text;

abstract class NewClass
{
	abstract public function test();
}
class AnotherClass(NewClass)
{
	public function test() {
	}
}
```


Add a single blank line before else statements if they are wrapped in curly braces.

Good:

```php
if ($variable == 1) {
	call_function($variable);

} else if ($variable == 2 && $new_var == 1) {
	$something = 3;
	call_function($something);

} else {
	$something = 1;
}
```

Bad:

```php
if ($variable == 1) {
	call_function($variable);
} else if ($variable == 2 && $new_var == 1) {
	$something = 3;
	call_function($something);
} else {
	$something = 1;
}
```


Whitespace in Expressions and Statements
----------------------------------------

Avoid extraneous whitespace in following situations:

- Immediately inside parentheses, brackets or braces:

Good: `function_call($param[0], array(1, 2));`

Bad:  `function_call( $param[ 0 ], array( 1 , 2 ) );`

- Immediately before comma:

Good: `$something = array(1, 2, 3, 4);`

Bad:  `$something = array(1 , 2 , 3 , 4);`

- Immediately before open parenthesis for indexes or argument list of a function call:

Good:

```php
$something = $array[1];
$new_var = function_call($something);
```

Bad:

```php
$something = $array [1];
$new_var = function_call ($something);
```

- More than one space around variable assignment:

Good:

```php
$var1 = 1;
$var2 = 2;
$long_variable = 3;
```

Bad:

```php
$var1 =          1;
$var2 =          2;
$long_variable = 3;
```

However, additional tab characters should be used to align key, value pairs when defining an array.

Good:

```php
$new_array = array(
		'0'		=> 'value',
		'new'	=> 'another_value'
	);
```


Other Recommendations
---------------------

Always surround binary operators with a single space on either side: assignment (=), augmented assignment (+=, -=,
etc.), comparisons (==, <, >, !=, <=, >=, etc.), booleans (&&, ||).


Multiple statements on a single line are _strongly_ discouraged.

Good:

```php
if ($something)
	do_call_function(); else
	another_function();
```

Bad:

```php
if ($something) do_call_function(); else another_function();
```


Do not wrap single line statements in curly braces in flow control commands. However if any of the sides has more than
one line of code it's okay to wrap single lines as well.

Good:

```php
if ($something)
	another_function();
```

Also good:

```php
if ($something) {
	another_function();

} else {
	$new_var = 3.14;
	call_function($new_var);
}
```

Bad:

```php
if ($something) {
	another_function();
}
```

Also bad:

```php
if ($something) {
	another_function();
} else {
	call_function($something);
}
```


C style of function declaration is not allowed! As this project is not written in C functions can be nested within
other object types. For this reason opening curly brace after function or class declaration needs to be at the same
line as the declaration itself.

Good:

```php
class NewClass {
	function Something($param) {
	}
}
```

Bad:

```php
class NewClass
{
	function Something ($param)
	{
	}
}
```


Comments
--------

Comments that contradict or don't describe the code are worse than no comments. Always keep comments up-to-date with
when the code changes. Comments need to be descriptive, simple in nature. It's recommended that comments are treated
like sentences but comments with too many words can disrupt code. If comment is a sentence it needs to start with
capital letter and end with punctuation sign.

Single phrase, short, comments should not be treated as sentences and should form a coherent thought with previous
comments of same type.

_All comments must be written in English!_


Block Comments
--------------

Block comments generally apply to some (or all) code that follows and are indented to the same level as code. Block
commends start with slash followed by two asterisks on first line. Each line is prepended by a single asterisk.

Good:

```php
/**
 * Block comment example.
 */
```

Bad:

```php
/*
 * Block comment example.
 */
```

Really bad:

```php
//////////////////
// Block comment example
```


Block comments that describe classes or are located at the beginning of the file need to have title and short
description of what following code does. This is also a good location for additional instructions related to file
or class in question. Each word in title of the comment is capitalized.

Example:

```php
/**
 * Database Connection Handler
 *
 * Object this class produces are used for generating and maintaining
 * connection pool for database backend. In normal use you will never have
 * to manually create these objects as they are automatically created
 * by the database itself.
 */
```


Inline Comments
---------------

Use inline comments sparingly. An inline comment is on the same line as a statement or on a separate line before it.
If inline comment is on a separate line it describes block of statements that follow. None of the inline commends
are to be treated as sentences and are written in lowercase with no period on the end.

If a comment is on the same line as statement it's preceded with two spaces and two forward slashes.

Inline comments should not be used to describe every line of code. It should be used only in places where effect of
the code is not immediately obvious. Avoid writing obvious inline comments.

Good:

```php
x = x + 1;  // compensate for border
```

Bad:

```php
x = x + 1; // increment x
```


Naming Styles
-------------

- Class names: Always use CapWords convention when naming classes. Treat acronyms as words (ex. Sql instead of SQL);
- Namespaces: Same as class names;
- Variable names: Lowercase words separated by underscore (ex. `$example_variable_name`);
- Constant names: Uppercase words separated by underscore (ex. `SOME_CONSTANT_NAME`);
- Function names: Same as variable names;


Programming Recommendations
---------------------------

Use single `return` statement per function. If function needs to return early store value and return at the end. This
forces programmer to keep the code simple and more readable.

Good:

```php
switch ($variable) {
	case 1:
		$result = 'a';
		break;

	case 2:
		$result = 'b';
		break;

	default:
		$result = null;
		break;
}

return $result
```

Bad:

```php
switch ($variable) {
	case 1:
		return 'a';

	case 2:
		return 'b';

	default:
		return null;
}
```


Proper use of exceptions. Throwing custom exceptions allows developers to properly handle errors in their own code
without having to resort to alternative methods of debugging.

Good:

```php
class CustomException extends Exception {}


throw new CustomException('Could not connect!');
```

Extremely bad:

```php
print "Error: Could not connect!";
```
