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
    MAY contain a CRLF (\\r\\n) which MUST be stripped by the requester before
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
        The file's SHA-256 hash, in hex form.
    length
        The file's length in bytes.
    name
        The file's name. This is unrelated to the name provided at
        ``uploads.finish`` and intended for display purposes to clients other
        than the requester which are interested in ongoing upload progress.
:Response:
    An Upload_ with a ``gcs_url`` field present.
:Errors:
    1000 size_limit_exceeded
        The server is configured to not accept files this large, or the file
        size provided cannot be losslessly stored in a IEEE 754
        double-precision floating point number.
    1001 in_progress
        An upload was started for a file with the same SHA-256 hash but not
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

The GCS URL returned is valid for 24 hours, and the upload as a whole is
expected to be finished in 24 hours. That said, if a resumable upload is
initiated, it may be possible to continue and finish uploading even after this
signed URL expires, given that it's only used to set up the upload session,
however, it will not be possible to call ``uploads.finish`` and the uploaded
stray file will be removed eventually by a cleanup job.

If ``uploads.finish`` isn't invoked within 24 hours of beginning the upload,
the upload will be automatically cancelled under the assumption that it has
failed transiently and the client cannot inform the server of the fact. This
applies even if the GCS upload becomes finished, but the server isn't informed
of the fact via ``uploads.finish``.

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
    to have ``indexing_state`` of 0. The File will have a ``type`` of ``null``
    until indexing is begun.
:Errors:
    1000 size_limit_exceeded
        The file uploaded exceeds the maximum size threshold that the server
        is configured to accept. This can only occur if the client provides
        untruthful data to ``uploads.begin``, or the server size limit is
        changed between the call to ``uploads.begin`` and the call to
        ``uploads.finish``.  In either case, the file is removed from GCS and
        the file ID is invalidated and MUST NOT be used again.
    2404 not_found
        The ``upload_id`` provided does not correspond to an in-progress upload.

After calling this method, ``files.get`` can be called periodically to check
the processing status and the resolved type of this file.

==============
uploads.cancel
==============

:Summary: Request the server to clean up any state associated with this file.
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

=======================
uploads.report_progress
=======================

:Summary: Store a progress report on the server to present to other clients.
:Params:
    upload_id
        The upload ID, as returned by ``uploads.begin``.
    progress_length
        The number of bytes currently uploaded. The progress indication is
        calculated using the ``length`` provided at the beginning of uploading.
:Response: ``null``
:Errors:
    2404 not_found
        The ``upload_id`` is invalid.

================
uploads.progress
================

:Summary: Get the latest progress report from the uploader.
:Params:
    upload_id
        The upload ID, as returned by ``uploads.begin``.
:Response: Float value in the range [0, 1] representing the upload progress.
:Errors:
    2404 not_found
        The ``upload_id`` is invalid.

============
uploads.list
============

:Summary: List all in-progress uploads.
:Params: None.
:Response: An array of Upload_ objects.
:Errors: None.

==========
files.list
==========

:Summary: List all uploaded files.
:Params: None.
:Response: An array of File_ objects.
:Errors: None.

TODO: This could probably use pagination.

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

============
files.delete
============

:Summary: Delete a file.
:Params:
    file_id
        The file ID.
:Response: ``null``
:Errors:
    2404 not_found
        The ``file_id`` is invalid.

The file might not be removed from GCS right away, but it will no longer show
up in ``files.list`` or in search results.

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
        An array (set) of string tags. This will replace any tags previously
        set so should be used with care to prevent race conditions from losing
        data.
    relevance_timestamp
        A Timestamp_. ``null`` to unset.

    The ``name``, ``tags`` and ``relevance_timestamp`` are optional; if not
    provided, the respective metadata attribute will not be changed.
:Response: The File_ object, after applying any metadata changes.
:Errors:
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

------
Upload
------

Fields:

id
    The upload ID.
hash
    The SHA-256 hash of the file in hex form.
progress
    A float in the range [0, 1] indicating the upload progress, as reported by
    the client.
name
    The tentative name of the upload. There's currently no mechanism to change
    this during the upload.
gcs_url
    The signed GCS upload URL to perform the upload to. This field is only
    present for Uploads returned from `uploads.begin`_.

----
File
----

Fields:

id
    The file ID.
name
    The file's literal display name.
tags
    An array of string tags. The order of the tags is not significant; this
    should be treated as a degenerate representation of a set.
upload_timestamp
    An Timestamp_ at which the upload of the file was begun.
relevance_timestamp
    An Timestamp_, as provided by the user. ``null`` if the relevance
    timestamp has not been set by the user.
length
    The size of the file in bytes.
hash
    The SHA-256 hash of the file in hex form.
type
    Either ``document``, ``plain`` or ``media``, depending on the document's
    specifics. This also determines the kind of search results returned. While
    ``indexing_state`` is 0, this can be ``null`` instead.
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

As a notable edge case, if the file has a different SHA-256 hash than is
expected, such an error will be detected at stage 0 *and will cause the file's
hash to be changed in the database and in the GCS URL* so that the upload can
be retried. In either case the file is treated as corrupt and scheduled for
removal as it would be in cases of other indexing errors.

------------
SearchResult
------------

In the interest of compactness of representation, the fields are abbreviated.

Fields:

i
    The File_ ID of the result.
f
    An excerpt of the document text, providing context for the search result.
r
    An array of Range_ objects pointing into ``f``.
p
    Present only for files whose type is ``document``: The integer page number
    of the page in the document at which ``f`` begins. ``p`` of 1 always
    corresponds to the first page in document linear order, even if the
    numbering scheme is customised.
t
    Present only for files whose type is ``media``: The float second timestamp
    at which ``f`` is heard. This is determined according to the PTS of the
    medium, when linearized by transcoding and synthesizing PTSes if necessary
    (not provided by the upload.) This might not be accurate for exotic media
    files.

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
