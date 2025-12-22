# Privacy template parsing - `cms:privacy`

Contents of this tag will be parsed only in cases where `type` attribute of the tag is listed in categories user accepted to allow on this site. By default there's `system` category, for which consent is not requied according to GDPR, GCP and others. European's GDPR is the only opt-in requirement when it comes to privacy and for that reason every other legislation is treated as such. This means that on initial page opening system _will by default skip_ rendering of all but `system` privacy tags. Once user accepts configuration in popup dialog page is refreshed, consent recorded for legal reasons and privacy sections rendered accordingly.

Tag `cms:privacy` supports the following attributes:

- `type` - Type of content contained within. You can only specify one type;

Predefined types are:

- `system` - For system scripts. Things like language preference, session tracking and similar don't require consent;
- `customization` - For customizing user experience (recommendations, AB testing, etc.). Consent required: Depends on implementation;
- `analytics` - For usage analytics and performance tracking. Consent required: GDPR: yes, USA: often;
- `advertising` - For advertising and targeting purposes. Consent required: GDPR: yes, USA: yes for sale/share.

You are able to customize this list and add more types by changing `privacy_consent.xml` from the site template and adding additional categories with their descriptions. All you need to do in templates now is use this new type as you would with built in types.

Example embedding scripts depending on their type:

```xml
<cms:privacy type="system">
	<script type="text/javascript" src="main.js">
</cms:privacy>
<cms:privacy type="customization">
    <script>(function(d,src){j=d.createElement('script'),j.defer=true;j.src=src;d.getElementsByTagName('script')[0].appendChild(j); })(document,'https://accessible.vagas.co.il/a/yuni/accessibility.js');</script>
</cms:privacy>
```
