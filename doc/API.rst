===========
Conventions
===========

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "NOT RECOMMENDED", "MAY", and "OPTIONAL" in this
document are to be interpreted as described to `IETF BCP 14 (RFC 8174)`_
when, and only when, they appear in all capitals, as shown here.

.. _IETF BCP 14 (RFC 8174): https://datatracker.ietf.org/doc/html/rfc8174

The API is structured as a `JSON-RPC <https://www.jsonrpc.org/specification>`_
endpoint, therefore JSON-RPC 2.0 conventions apply. The only exception to this
is the ``POST /auth-token`` endpoint, as documented below.

The JSON-RPC request must be sent as POST to the ``/rpc`` endpoint of the
server, using the UTF-8 charset for body encoding.

All JSON-RPC methods accept parameters only using the by-name convention, i.e.,
the ``params`` MUST be an Object with the names as provided. Batch requests (as
per JSON-RPC_ §6) MUST NOT be sent to the RPC endpoint.

The presence or absence of an ``id`` field determines whether the call is a
notification or a request. If the call is a notification, a success response
SHALL have HTTP status 204 No Content and no body; otherwise, a success
response SHALL have HTTP status 200 OK and a JSON-encoded body as appropriate,
using the UTF-8 charset. All aftdocumented endpoints MUST be requested as method
calls unless noted otherwise therein.

All requests to the JSON-RPC endpoint MUST contain an auth token in the header,
which is obtained from ``POST /auth-token``.

::

    Authorization: Bearer <<auth token>>

======
Errors
======

As per JSON-RPC, the errors are reported using the ``error`` field of the
response. The standard codes (-32700, -32600, -32601, -32602) are returned as
per the spec. The request-specific codes are allocated in the 1000..1999 range.
If an error occurs, the HTTP status code is either 400 or 500, as per
circumstances; in particular, a non-4xx and non-5xx response code indicates
that the request completed successfully and the resposne object contains a
``result`` field (which in some cases might be ``null`` if the request doesn't
garner a response, as documented.)

If an error occurs, the ``message`` field contains a user-friendly error
message in the server's default language.

If an error maps cleanly onto an HTTP response code (such as 404 Not Found),
the error code is 2000 + the response code (e.g., HTTP 404 turns into code
2404.) Regardless, the HTTP response code is set to 400 or 500 as appropriate.
This implies that a non-400 and non-500 response code in the 4xx or 5xx ranges
indicates a non-API failure, such as a 503 indicating a server configuration
failure, or 413 indicating an overly large request body.

The errors that a particular method invocation can return are listed therein.
Two errors can be returned by any invocation and therefore aren't repeated in
the documentation, and don't entirely abide by the rules outlined above.

Any invocation can return error 2429 with the HTTP status code ``429 Too Many
Requests`` if the requester is being rate-limited. The response contains a
``Retry-After`` header which contains an integer number of seconds, only after
which elapse the request should be retried. The number of seconds is also
provided as the ``data`` key of the error. The rate limit is not token-specific
and cannot be bypassed by swapping tokens or minting a new token (in fact, the
token minting is also protected by the same rate limit.)

::

    {
        "jsonrpc": "2.0",
        "id": "1",
        "error": {
            "code": 2429,
            "message": "Too many requests, try again in about three minutes.",
            "data": 170
        }
    }

Any invocation can return error 2401 with the HTTP status code ``401
Unauthorized`` if the requester does not provide an auth token or the provided
auth token has expired -- in the latter case, the token SHOULD be re-requested
by ``POST /auth-token`` and the request SHOULD be retried with the new token.
There is no ``data`` key.

::

    {
        "jsonrpc": "2.0",
        "id": "1",
        "error": {
            "code": 2401,
            "message": "The request didn't contain a required security token, or the token provided has expired. Please try restarting the app."
        }
    }

================
POST /auth-token
================

**Important:** This endpoint does not return a JSON-RPC formatted response, and
does not accept a JSON-RPC formatted request.

:Summary: Generate a short-lived token used to authenticate subsequent requests.
    The purpose is similar to a CSRF token -- not to authenticate the consumer
    long-term, but to prevent arbitrary unintended requests from triggering any
    actions or wasting server resources.
:Body: plaintext: the application API secret
:Response: plaintext: the token to include in further requests. The response
    MAY contain a CRLF (\r\n) which MUST be stripped by the requester before
    using the remainder of the body verbatim as a token.
:Errors: - HTTP status 429: The requester is being rate-limited. The request
           should be retried no sooner than after the number of seconds
           specified in the ``Retry-After`` seconds.
         - HTTP status 401: The provided application API secret is invalid. The
           request should not be retried.

         If the requester provides an invalid application API secret *and* is
         being rate-limited, HTTP status 429 takes precedence.

         In either case, the response body is a plaintext user-friendly message
         in the server's default language.

The token returned from the response should hereinafter be provided in
subsequent requests with the Authorization header:

::

    Authorization: Bearer <<token>>

The token need not be stored long-term. The token will expire after about 1
hour of inactivity, however, the server may forget the token earlier than that,
in which case it should be requested again before proceeding; as such, the
client SHOULD request a new token on every startup, and SHOULD forget an active
token after 30 minutes of inactivity elapse.

=============
uploads.begin
=============

:Summary: Mint a GCS signed target URL to perform a file upload onto. The
    logistics of the upload and long-term storage are handled by GCS, not by
    the API server.
:Params:
    hash
        The file's BLAKE3 hash, in hex form.
    length
        The file's length in bytes.
:Response:
    upload_id
        An upload ID. This is different from a file ID.
    upload_url
        The GCS URL to perform the upload to. ``null`` if the file is already
        uploaded (its BLAKE3 hash matches a known file.)
:Errors:
    1000 size_limit_exceeded
        The server is configured to not accept files this large, or the file
        size provided cannot be losslessly stored in a IEEE 754
        double-precision floating point number.
    1001 in_progress
        An upload was started for a file with the same BLAKE3 hash but not
        finished. The ``data`` field is set to the upload ID of the in-progress
        upload so that it can be cancelled, though that MUST be performed only
        with user consent and outlining the potential data loss.
    2409 conflict
        The file with the given hash has already been successfully uploaded. The
        ``data`` field is set to the file ID of the relevant file.

The file's uploading timestamp will be set to the time at which uploading was
begun, not at which it was finished.

After the upload is finished, ``uploads.finish`` should be invoked. If the
upload is cancelled, ``uploads.cancel`` should be invoked to remove the
in-progress upload.

If ``uploads.finish`` isn't invoked within 24 hours of beginning the upload,
the upload will be automatically cancelled under the assumption that it has
failed transiently and the client cannot inform the server of the fact. Any
partial upload data is removed.

==============
uploads.finish
==============

:Summary: Report to the server that a file upload has been finished and any
    necessary processing can begin. This MUST be called only after the entire
    file is uploaded and finalized.
:Params:
    upload_id
        The upload ID, as returned by ``uploads.begin``.
    name
        The file's name, as provided by the filesystem or set by the user
        interim ``uploads.begin`` and now.
    tags
        An array of string tags to be applied to the file.
    relevance_timestamp
        The relevance timestamp for the file, as specified by the user, in the
        same format as a Timestamp_.
:Response: A File_ object, corresponding to the upload. The File is guaranteed
    to have ``indexing_state`` of 0.
:Errors:
    -32602 invalid_params
        A parameter was absent or of the wrong type (e.g., ``tags`` not being
        an array of strings, or ``relevance_timestamp`` not being in RFC 3339
        format.) If the ``data`` field is present, it lists an array
        of the field names that were invalid.
    1000 size_limit_exceeded
        The file uploaded exceeds the maximum size threshold that the server
        is configured to accept. This can only occur if the client provides
        untruthful data to ``uploads.begin``, or the server size limit is
        changed between the call to ``uploads.begin`` and the call to
        ``uploads.finish``.  In either case, the file is removed from GCS and
        the file ID is invalidated and MUST NOT be used again.
    2404 not_found
        The ``upload_id`` provided does not correspond to an in-progress upload.

After calling this method, ``files.check_indexing_progress`` can be called
periodically to check the processing status of this file.

==============
uploads.cancel
==============

:Summary: Request the server to clean up any state associated with this file,
    including deleting any partial uploaded data from GCS.
:Params:
    upload_id
        The upload ID, as returned by ``uploads.begin``.
:Response: ``null``
:Errors:
    2404 not_found
        The ``upload_id`` provided does not correspond to an in-progress upload.

Calling this method will also invalidate the upload ID such that it will return
a 2404 error upon future calls to this method or ``uploads.finish``.

This method can be called even if the file upload has finished to prevent
indexing. This method will invariably incur some data loss so MUST be invoked
only with user consent or under irreparable circumstances.

=============================
files.check_indexing_progress
=============================

:Summary: Return information on the current indexing state of the uploaded
    document.
:Params:
    file_id
        The file ID.
:Response: An integer indexing state, as per documentation of File_.
:Errors:
    2404 not_found
        The ``file_id`` is invalid.

========================
files.get_indexing_error
========================

:Summary: Get a description of where indexing a file has failed. This is
    possible only for `Files <File>`_ with the indexing state -1.
:Params:
    file_id
        The file ID.
:Response:
    stage
        The integer indexing state at which the error occured, corresponding to
        the ones defined for File_.
    message
        A human-readable summary of what went wrong.
    log
        A string containing output from the indexing that might be useful for
        debugging.
:Errors:
    1002 state_error
        The ``file_id`` in question has not failed indexing (yet.)
    2404 not_found
        The ``file_id`` is invalid.

=========
files.get
=========

:Summary: Get relevant metadata for a file.
:Params:
    file_id
        The file ID.
:Response: A File_ object.
:Errors:
    2404 not_found
        The ``file_id`` is invalid.

======================
files.request_download
======================

:Summary: Obtain a download URL for the file, in case a local copy is needed.
:Params:
    file_id
        The file ID.
:Response: A string URL from which the file can be downloaded.
:Errors:
    2404 not_found
        The ``file_id`` is invalid.

The download URL should be treated as though it will be valid for no more than
24 hours.

==========
files.edit
==========

:Summary: Change the user-editable metadata of a file.
:Params:
    file_id
        The file ID.
    name
        The new filename of the file.
    tags
        An array of string tags. This will replace any tags previously set so
        should be used with care to prevent race conditions from losing data.
    relevance_timestamp
        A Timestamp_ or ``null`` if not set.
:Response: The File_ object, after applying any tag changes.
:Errors:
    -32602 invalid_params
        A parameter was absent or of the wrong type (e.g., ``tags`` not being
        an array of strings, or ``relevance_timestamp`` not being in RFC 3339
        format.) If the ``data`` field is present, it lists an array
        of the field names that were invalid.
    2404 not_found
        The ``file_id`` is invalid.

===============
files.edit_tags
===============

:Summary: Atomically change a file's tags.
:Params:
    file_id
        The file ID.
    add
        An array of string tags to attach to a file.
    remove
        An array of string tags to remove from a file.
:Response: The File_ object, after applying any tag changes.
:Errors:
    2404 not_found
        The ``file_id`` is invalid or does not correspond to a finished upload.

If a tag is specified both in the ``add`` and the ``remove`` lists, the tag will
not change attachment (if it was attached, it will remain attached, and vice
versa.) If a tag is specified in the ``remove`` list which is not attached to
a file, the tag will not change attachment.

==============
search.perform
==============

:Summary: Perform a search against the index.
:Params:
    search_query
        The string search query. The semantics of the query string are not
        presently defined.
:Response: An array of SearchResult_ objects.
:Errors:
    1003 syntax_error
        The search query contains invalid syntax. The semantics of this error
        are not presently defined.

============
Common types
============

---------
Timestamp
---------

A string containing an `RFC 3339 <https://datatracker.ietf.org/doc/html/rfc3339>`_
datetimestamp with second precision. The timestamp MUST be in the UTC timezone
and therefore the timezone suffix MUST be ``Z``. The date/time separator MUST
be the symbol ``T``.

----
File
----

Fields:

id
    The file ID.
name
    The file's literal name, as set during the upload.
tags
    An array of string tags.
upload_timestamp
    An Timestamp_ at which the upload of the file was begun.
relevance_timestamp
    An Timestamp_, as provided by the user. ``null`` if the relevance
    timestamp has not been set by the user.
length
    The size of the file in bytes.
hash
    The BLAKE3 hash of the file.
type
    Either ``document``, ``plain`` or ``media``, depending on the document's
    specifics. This also determines the kind of search results returned.
indexing_state
    An integer, as per below.
removal_deadline
    Only present if ``indexing_state`` is -1. A Timestamp_ after which the file
    will be removed from GCS and from the database, and after which the file ID
    will become invalid.

After uploading, a file can be in one of these indexing states:

0
    The file is in queue waiting to be indexed.
1
    The file is being parsed to extract its contents. For media files, this
    indicates only transcoding the audio into a transcription-compatible format.
2
    A media file is pending transcription by an external service. This state is
    never set for documents.
3
    The file is pending to be added to the search index.
4
    The file has been fully indexed and is at rest.
\-1
    An error has occured during transcoding, extraction or indexing.

If the file enters the -1 state, it becomes pending for removal as though it
was uploaded but not indexed, and its ``removal_deadline`` is set to 24 hours
after the error occured. The particular error can be obtained using
``files.get_indexing_error``.

------------
SearchResult
------------

An object with one and only one of these fields present:

media
    An array of `SearchResult.Media`_ objects.
document
    An array of `SearchResult.Document`_ objects.
plain
    An array of `SearchResult.Plain`_ objects.

In the interest of compactness, the particular search result fields are
abbreviated: ``f`` is short for "fragment", ``r`` is short for "ranges", and
``p``, where applicable, is short for ``position``.

^^^^^^^^^^^^^^^^^^
SearchResult.Plain
^^^^^^^^^^^^^^^^^^

Fields:

f
    An excerpt of the document text, providing context for the search result.
r
    An array of Range_ objects pointing into ``fragment``.

Note that plain documents don't intend for more specific delineation of position
short of a direct full search of the document, and given that, no more
particular locators are provided and the ``p`` field is absent.

^^^^^^^^^^^^^^^^^^^^^
SearchResult.Document
^^^^^^^^^^^^^^^^^^^^^

Fields:

f
    An excerpt of the plaintext of the document, providing context for the
    search result.
r
    An array of Range_ objects pointing into ``fragment``.
p
    The integer number of the page in the document at which the ``fragment``
    begins. This numbering is unrelated to non-standard PDF numbering schemes;
    ``page`` of 1 always corresponds to the first page in document linear order,
    whatever its assigned number.

^^^^^^^^^^^^^^^^^^
SearchResult.Media
^^^^^^^^^^^^^^^^^^

Fields:

f
    An excerpt of the transcription of the media, providing context for the
    search result.
r
    An array of Range_ objects pointing into ``fragment``.
p
    The timestamp in seconds at which ``fragment`` begins. This is determined
    according to the PTS of the medium, when linearized by transcoding and
    synthesizing PTSes if necessary (not provided by the upload.) This might
    not be accurate for exotic media files.

-----
Range
-----

A ``Range`` is an array of two integers, ``start`` and ``end``. Both correspond
to character (code point) indices---not byte indices---into the ``fragment``
string, both points inclusive, to denote an area of interest, notably, to
highlight a search result.

Some examples follow that can also be used as basic test cases. Note that these
show only one range; it's possible for multiple ranges to be present, though
they shall not intersect -- two adjacent ranges are guaranteed to be merged
into a larger range, inclusive of both endpoints.

::

    String (literal):           apple banana carrot durian
    String (hex-escaped UTF-8): apple banana carrot durian
    Range:                      [6 11]
    Highlight fragment:         banana
    String (with highlight):    apple <<banana>> carrot durian

    String (literal):           ābols banāns
    String (hex-escaped UTF-8): \xC4\x81bols ban\xC4\x81ns
    Range:                      [0 4]
    Highlight fragment:         ābols
    String (with highlight):    <<ābols>> banāns

    String (literal):           hello 你好 čau
    String (hex-escaped UTF-8): hello \xE4\xBD\xA0\xE5\xA5\xBD \xC4\x8Dau
    Range:                      [6 10]
    Highlight fragment:         <<好 ča>>
    String (with highlight):    hello 你<<好 ča>>u

    String (literal):           lol 🤣 so funy
    String (hex-escaped UTF-8): lol \xF0\x9F\xA4\xA3 so funy
    Range:                      [4 7]
    Highlight fragment:         <<🤣 so>>
    String (with highlight):    lol <<🤣 so>> funy
