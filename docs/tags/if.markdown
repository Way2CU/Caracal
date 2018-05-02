# Conditional template parsing - `cms:if`

Contents of this tag will be parsed only in cases where conditions are evaluated to true. There are different tag attributes that can be specified and tested as part of the condition.

Tag `cms:if` supports the following attributes of which one or more can be specified:

- `section` - Test if value specified in this attribute matches current system section. Sections are to be considered _deprecated_ since version 0.5 of the system. Currently they contain few different values only and will be removed by next version;
- `page_template` - Test if URL matched template file name matches value of the attribute;
- `condition` - Raw PHP code to be evaluated for its trueness;

Example parsing optional image only on home page:

```xml
<cms:if page_template="home.xml">
	<img
		alt="Example image"
		src="images/something.png"
		/>
</cms:if>
```
