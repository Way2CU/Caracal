Coding Style
============

It's a well known fact that code is read much more often than it is written. The guidelines provided here are intended to improve the readability of the code and make it consistent within this project. Consistency is important but code readability has higher priority. If applying the rule would make code less readable it's okay to ignore the rule.

And don't hesitate to ask!

_Note:_ This document was written based on great [PEP8](http://www.python.org/dev/peps/pep-0008/) proposition.

Contents:

1. [PHP coding style](#php)
	- [Indentation](#php/indentation)
	- [Blank lines](#php/blank-lings)
	- [Whitespace in expressions and statements](#php/white-space)
	- [Other recommendations](#php/recommendations)
	- [Comments](#php/comments)
		- [Block comments](#php/block-comments)
		- [Inline comments](#php/inline-comments)
	- [Naming convention](#php/naming)
	- [Programming recommendations](#php/programming-recommendations)
2. [CSS coding style](#css)
	- [General layout](#css/general-layout)
	- [Selectors](#css/selectors)
	- [Legacy support](#css/legacy)
3. [XML coding style](#xml)


# <a name="php">PHP coding style</a>


## <a name="php/indentation">Indentation</a>

Use tab characters instead of spaces. Ideally 4 spaces per tab. Tab characters allow developers to use different indentation sizes while still respecting the coding style rules. _Never_ use spaces instead of tab character!

Suggested maximum line length is 120 characters. If code is getting close to this limit it means code is getting too complex and can be changed to be much simpler.

Long lines, complex flow control statements and variable definitions should be wrapped vertically. When using a hanging indent avoid arguments on first line. Closing brace/bracket/parenthesis in this case must be on a separate line with one less indent level.

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

If a function definition is too long, break extra parameters vertically and approximately align to the first parameter of the definition. However whenever possible, avoid breaking function definitions.

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


## <a name="php/blank-lines">Blank lines</a>

When defining classes, two blank lines before and after class code is required. This makes files with more than one class easier to read and visually search. Functions inside of class are separated with one blank line. Extra blank lines can be used sparingly to group code inside of functions or to group imports.

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


## <a name="php/white-space">Whitespace in expressions and statements</a>

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


## <a name="php/recommendations">Other recommendations</a>

Always surround binary operators with a single space on either side: assignment (=), augmented assignment (+=, -=, etc.), comparisons (==, <, >, !=, <=, >=, etc.), booleans (&&, ||).

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


Do not wrap single line statements in curly braces in flow control commands. However if any of the sides has more than one line of code it's okay to wrap single lines as well.

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

C style of function declaration is not allowed! As this project is not written in C functions can be nested within other object types. For this reason opening curly brace after function or class declaration needs to be at the same line as the declaration itself.

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


## <a name="php/comments">Comments</a>

Comments that contradict or don't describe the code are worse than no comments. Always keep comments up-to-date with when the code changes. Comments need to be descriptive, simple in nature. It's recommended that comments are treated like sentences but comments with too many words can disrupt code. If comment is a sentence it needs to start with capital letter and end with punctuation sign.

Single phrase, short, comments should not be treated as sentences and should form a coherent thought with previous comments of same type.

_All comments must be written in English!_


### <a name="php/block-comments">Block comments</a>

Block comments generally apply to some (or all) code that follows and are indented to the same level as code. Block commends start with slash followed by two asterisks on first line. Each line is prepended by a single asterisk.

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

Block comments that describe classes or are located at the beginning of the file need to have title and short description of what following code does. This is also a good location for additional instructions related to file or class in question. Each word in title of the comment is capitalized.

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


### <a name="php/inline-comments">Inline comments</a>

Use inline comments sparingly. An inline comment is on the same line as a statement or on a separate line before it.  If inline comment is on a separate line it describes block of statements that follow. None of the inline commends are to be treated as sentences and are written in lowercase with no period on the end.

If a comment is on the same line as statement it's preceded with two spaces and two forward slashes.

Inline comments should not be used to describe every line of code. It should be used only in places where effect of the code is not immediately obvious. Avoid writing obvious inline comments.

Good:
```php
x = x + 1;  // compensate for border
```

Bad:
```php
x = x + 1; // increment x
```


## <a name="php/naming">Naming convention</a>

- Class names: Always use CapWords convention when naming classes. Treat acronyms as words (ex. Sql instead of SQL);
- Namespaces: Same as class names;
- Variable names: Lowercase words separated by underscore (ex. `$example_variable_name`);
- Constant names: Uppercase words separated by underscore (ex. `SOME_CONSTANT_NAME`);
- Function names: Same as variable names;


## <a name="php/programming-recommendations">Programming recommendations</a>

Use single `return` statement per function. If function needs to return early store value and return at the end. This forces programmer to keep the code simple and more readable.

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

Proper use of exceptions. Throwing custom exceptions allows developers to properly handle errors in their own code without having to resort to alternative methods of debugging.

Good:
```php
class CustomException extends Exception {}


throw new CustomException('Could not connect!');
```

Extremely bad:
```php
print "Error: Could not connect!";
```


# <a name="css">CSS coding style</a>

These coding style guide lines also apply to LESS and SASS should they be used in a project. All tags and selectors must be written in lower case to keep the consistency and readability.


## <a name="css/general-layout">General layout</a>

Selectors and properties contained within are to be written in vertical direction. Horizontal and cascading style are not allowed. This is to ensure readability of the code regardless of its size. Selectors are to be spaced by a single empty line. Properties inside of selector body are indented by a single tab. Property name is followed by color and a single space.

Good:
```css
div,
div > button {
	padding: 1em 3em;
}

div.box {
	display: block;
	border-color: blue;
}
```

Bad:
```css
div {
	padding:1em 3em;
}
```

Also bad:
```css
  div {
	padding: 1em 3em;
}
```

Extremely bad:
```css
div, div > button { padding: 1em 3em; }
div.box { display: block; border-color: blue; }
```

Also extremely bad:
```css
div { padding: 1em 3em; }
	div > button { padding: 1em 3em; }
div.box { display: block; border-color: blue; }
```


## <a name="css/selectors">Selectors</a>

Selectors need to ordered by their specificity and grouped by similarity. This ensures code is easy to find and change later in the process. This way of coding also ensures any ambiguity about priority of selectors is avoided.

Good:
```css
form {
	...
}

form label {
	...
}

form label span {
	...
}
```

Bad:
```css
form {
	...
}

form label span {
	...
}

form label {
	...
}
```

This rule also applies to pseudo-selectors even though it doesn't change the behavior sometimes.

Good:
```css
input {
	...
}

input:focus {
	...
}
```

Bad:
```css
input:focus {
	...
}

input {
	...
}
```


## <a name="css/legacy">Legacy support</a>

Whenever possible avoid usage of vendor specific prefixes for behavior not present in other browsers. If property is officially available in one browser, but available with prefix in others, then use of prefixes is allowed. Order of these properties must always be vendor specific followed by generic property to ensure site doesn't break once browser removes prefix and starts applying generic definition.

Browser specific property values must be defined as a separate property to avoid issues where browsers will ignore property in its entirety due to, that browser, invalid value.

Good:
```css
div {
	background-image: -webkit-linear-gradient(...);
	background-image: linear-gradient(...);
	-webkit-transform: translateX(10px);
	transform: translateX(10px);
}
```

Bad:
```css
div {
	backgroung-image: -webkit-linear-gradient(...), linear-gradient(...);
	transform: translateX(10px);
	-webkit-transform: translateX(10px);
}
```

Also bad:
```css
div {
	background-image: linear-gradient(...);
	background-image: -webkit-linear-gradient(...);
	transform: translateX(10px);
	-webkit-transform: translateX(10px);
}
```

Pseudo-selectors such as `@keyframes` must be separated to avoid same issues with browser specific properties and values.

Good:
```css
@-webkit-keyframes test {
	from {
		left: 0px;
	}

	to {
		left: 100px;
	}
}

@keyframes test {
	from {
		left: 0px;
	}

	to {
		left: 100px;
	}
}
```

Bad:
```css
@keyframes test,
@-webkit-keyframes test {
	from {
		left: 0px;
	}

	to {
		left: 100px;
	}
}
```

In cases where browser version must be supported but property or value with vendor prefix is not available graceful fallback must be provided. The way in which fallback option is provided can be visually unappealing as long as functionality is still present.

Example:
```css
div {
	background-color: blue;
	background-image: linear-gradient(90%, blue, cyan);
	color: white;
}
```

In previous example, early versions of Internet Explorer would render solid `blue` container in place where there should be gradient. While looks are not the same general look of the site will remain the same. Further more color will provide good contrast for text in container. Should have `background-color` been omitted, container could have unreadable text and look broken to the user.


# <a name="xml">XML coding style</a>

All tags must be written in lower case to keep the consistency and readability of the code. Long lines need to be broken up into multiple splitting at the attribute. Tags of block elements need to be isolated on the line unless they are empty in which case they can be self-closing.

Good:
```xml
<div>
	<input
		type="text"
		name="test"
		maxlength="10"
		/>
	<input type="text" name="another" maxlength="10"/>
</div>
<div class="container"/>
```

Bad:
```xml
<div><input
		type="text"
		name="test"
		maxlength="10"
		/></div>
<div class="container"></div>
```

Also bad:
```xml
<div><input type="text" name="test" maxlength="10"/></div>
<div class="container"></div>
```
