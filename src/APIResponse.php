<?php

namespace Versium\Reach;

class APIResponse
{

    public
    //region append and listgen response properties
        /**
         * A curl error number, returned from curl_errno()
         * @int
         */
        $requestErrorNum = 0,

        /**
         * A curl error string, returned from curl_error()
         * @string
         */
        $requestError = '',

        /**
         * Set to true if the server responds with a 200 code. Can be used to quickly determine whether the
         * API request was successful or not.
         * @bool
         */
        $success = false,

        /**
         * The json_decoded response body for the API request. Utilized by the append function and error responses from
         * the listgen function.
         * @object
         */
        $body = '',

        /**
         * The raw response body for the API request. Utilized by the append function and error responses from the
         * listgen function.
         * @string
         */
        $bodyRaw = null,

        /**
         * The HTTP response status code for the API request.
         * @int
         */
        $httpStatus,

        /**
         * The array of inputs given for searching or filtering by.
         * @array
         */
        $inputs,
    //endregion

    //region append response only properties
        /**
         * Utilized by the append function and set to true if any matching records were found and returned.
         * @bool
         */
        $matchFound = false,
    //endregion

    //region listgen response only properties
        /**
         * Utilized only by the listgen function. This property is a function that returns a generator to be used for
         * iterating through the response records.
         * @function
         */
        $getRecordsFunc = null;
    //endregion


    /**
     * Allow class properties to be populated by an array
     * @param array $fields
     */
    public function __construct(array $fields = []) {
        if (!empty($fields)) {
            $validFields = get_class_vars(self::class);
            $fields = array_intersect_key($fields, $validFields);

            foreach ($fields as $field => $value) {
                $this->{$field} = $value;
            }
        }
    }

    /**
     * Convert class to array in order to utilize those juicy PHP array functions.
     * @return array
     */
    public function toArray(): array
    {
        return (array)$this;
    }
}
