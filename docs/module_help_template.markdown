# Module Title

Generic text describing module's primary purpose. If module doesn't have multiple language
support it needs to be mentioned here.

Summary:

1. Functions
	1.1. Public functions
	1.2. AJAX functions
2. Database structure
3. Contact


## 1. Functions

### 1.1. Public functions

#### 1.1.1. Function name

Function description.

	<cms:module
		name="module"
		action="function_name"
		required-param="value"
		optional-param="value"
	/>

**Param name**   | **Type** | **Required** | **Default** | **Description**
`required-param` | `int`    | +            |             | Param description
`optional-param` | `string` |              |             | Param description


Available template parameters:

**Param name** | **Type** | **Description**
`id`           | `int`    | Template param description
`text_id`      | `string` | Template param description


### 1.2. AJAX functions

### 1.2.1. Function name

Function description.

**Param name** | **Type** | **Required** | **Description**
required-param | `int`    | +            | Param description
optional-param | `string` |              | Param description

Description of return type.


## 2. Database structure

### 2.1. Table name

Short description of what is stored in the table. If there are any foreign relations they should be
mentioned as well.

**Field name** | **Type**     | **Size** | **Required** | **Default value** | **Description**
example        | `varchar`    | 250      | +            |                   | Example field description
example        | `ml_varchar` | 250      | +            |                   | Example field description


## 3. Contact

This section is required only for third party developers. Make sure you provide at least one email address.
