Allows developers to source content for wiki pages from places other than the local database.

Configuration options
---------------------

#### $wgMFContentProviderClass

Name of PHP class that is responsible for formatting HTML for mobile.
Must implement IContentProvider.

* Type: `string`
* Default: `DefaultContentProvider`

#### MFContentProviderTryLocalContentFirst

When using a ContentProvider in MFContentProviderClass, specify whether you want to allow local content as well as provided content. This is useful if you are wanting to run Selenium browser tests against locally created content but also have the benefit of testing content on a production wiki.

* Type: `boolean`
* Default: `true`

#### MFContentProviderScriptPath

When set will override the default script path to a foreign content provider
e.g.
`https://en.wikipedia.org/w`
will route queries (e.g. API) to English Wikipedia.

Note, this will make the wiki read only. Non-anonymous HTTP requests will throw CORS error.
This may also cause compatibility problems with other extensions.
This should not be used in production, it is strictly for development purposes.

* Type: `string`
* Default: ''

#### $wgMFMwApiContentProviderBaseUri

URL to be used by the MwApiMobileFormatter class. Points to a MediaWiki
API that can be queried to obtain content.

* Type: `string`
* Default: `https://en.wikipedia.org/w/api.php`

#### $wgMFAlwaysUseContentProvider

When enabled the ContentProvider will run on desktop views as well as mobile views.

* Type: `boolean`
* Default: `false`