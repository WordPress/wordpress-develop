# IndexNow

## Introduction

Indexnow is a functionality which enables website owners to submit their added, updated or deleted content instantly for indexing to all search engines. Wordpress users will get the benefit of having Indexnow functionalities for Bing, Yandex and Baidu already activated for them so that their content can be submitted directly to these search engines.

## Trigger initiation 

The default state for all the users would be on and users can opt out if needed. In order to opt out, user needs to create flag `WP_INDEXNOW` as `false` in the wp-config.php file. Also the endpoints to which the content is getting submitted can be edited (to insert a new endpoint) using the `WP_INDEXNOW_PROVIDERS` constant in wp-settings.php.

## Constants

* `WP_INDEXNOW` - Flag to be set to true in wp-config to enable IndexNow

* `WP_INDEXNOW_PROVIDERS` - Array containing the endpoints of IndexNow supporting search engines

## Functions 

List of public functions which can used by the developers: 

* `wp_indexnow_regenerate_key()` - Regenerates the indexnow api key and returns it. 

* `wp_indexnow_get_api_key()` - Returns the indexnow api key. 

* `wp_indexnow_ignore_path( $path )` - Adds url subpath to be ignored by indexnow. 

* `wp_indexnow_remove_path( $path )` - Remove url subpath added earlier using wp_indexnow_ignore_path(). 


To allow users to exclude certain sub-paths in their site, Indexnow allows user to add regular expressions for those sub-paths.  

For example,  

```
1) To exclude all the urls under www.example.org/test/, 

the user needs to add the regex `"/^\/test\//"`. This will exclude all paths starting with `"/test/"`, such as, 

www.example.org/test/ 

www.example.org/test/abc 

www.example.org/test/abc/any 

www.example.org/test/* 

2) If the user wants to exclude any url containing "/test/", for example, 

www.example.org/abc/test/xyz/ 

www.example.org/sample/post/test/in/ 

www.example.org/sample/test/ 

User simply needs to add "/\/test\//" regex to exclude the above urls. 

The user does not need to add the site url to the regex.  

The regular expression only applies to the path after the site url (here, www.example.org). 
```