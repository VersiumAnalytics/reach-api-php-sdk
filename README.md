<h2>Using Versium REACH with PHP</h2>
Once you have cloned the repo, all you need is the Versium_REACH.php file.   
To perform an API call, all you need to do is:   
1. Create an instance of the VersiumREACH class   
2. call the `append` function using the instance of the class   
   
Make sure to check the comments in Versium_REACH.php for more information on the parameters of the `append` function   
   
<h4>Things to keep in mind</h4>   
- It is up to you to make sure you do not exceed the api call rate limit. The default rate limit is 20 queries per second   
- You must have a provisioned API key for this function to work. If you are unsure where to find your API key, look at our [API key documentation](https://api-documentation.versium.com/docs/find-your-api-key)   