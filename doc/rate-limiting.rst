Consider this a quick thought dump because I happen to not have a better place to write this down yet.

===============
Implementations
===============

A rate limit sounds simple on the surface but doing it *well* takes a lot of effort behind the scenes. Allow me to illustrate.

The most basic approach to rate limiting is what I'll call a "simple counter". It works thusly:

1. Upon every request, extract its IP address and other information, and map this onto a *key* which is unique per the requester.
2. Atomically increment a value in the cache storage corresponding to the *key*. Iff it doesn't exist, set the value at *key* to 1, and set its expiry period to the *limiting interval*. Return the value at the key after the operation.
3. If the value is past the *limiting threshold*, apply the rate limit and deny the request.

(It's also possible to set the timeout upon every request or increment, but this makes the token cumulative, and non-bursty but periodic requests arriving at a rate lower than the *limiting interval* are guaranteed to hit it eventually in that case.)

This is very simple to implement and as a result fairly foolproof. But, while simple for the server, this is suboptimal -- a well-timed burst right around the time the limit expires can almost twice-over exceed the *limiting threshold*, given that the counter will be cleared at that point and the rate limiting will begin anew.

A widely used alternative that's resistant to this is the `leaky bucket <https://en.wikipedia.org/wiki/Leaky_bucket>`_ algorithm. In this case, the algorithm works thusly (though I'll admit this is my particular interpretation using what most cache drivers like Redis would allow for):

1. Upon a request, extract its information to map it onto a *key*, and also take note of the *timestamp* of the request.
2. Atomically, perform the bucket update:
    a) Iterate from the start of the bucket, removing any *item* whose value is less than *timestamp* minus *limiting interval*.
    b) Check whether the bucket length is greater than or equal to the *limiting threshold*. If yes, apply the rate limit and deny the request, and return.
    c) Iff the bucket length is less than the *limiting threshold*, append the *timestamp* to the list's end.
3. Iff 2.b) did not return, allow the request to proceed.

The upside of a leaky bucket is that, from the client's perspective, the behaviour of the service is much more consistent -- they can perform a set amount of requests in any given period, and the period window slides forward as the older requests go beyond the limiting interval. (This is strictly equivalent to a `token bucket <https://en.wikipedia.org/wiki/Token_bucket>`_ where it begins filled with the token count set to *limiting threshold* initially.)

The downside, obviously, is the fact that this requires more state to be kept and more code to perform. Notably, to perform step 2 atomically---even though this isn't a strict requirement, it can be performed non-atomically but be prone to race conditions in that case---it's either necessary to take out a lock for the duration, or use a cache system which supports transactions. But as such, most RDBMSes and also Redis (using Lua scripting) can support this strategy.

====================
Other considerations
====================

One problem I don't see covered nearly enough is the part that I glossed over previously: about mapping a request's data onto a *key*.

In ye olden days of IPv4, one simple way to do this was to just map every IPv4 address onto a unique key, perhaps even simply using the decimal serialization of the four octets. And this continues to work for the most part, even though it might be partially annoying for users behind CGNAT that might get unfairly penalized for actions that their network peers took and they had no influence over.

However, with IPv6 and the basically unlimited availability of /64 subnets to even residential users (or at least the fact that it's intended to be this way), a /128 address is basically disposable, so doing the naive approach might not be sufficient, and bypassing the rate limit is trivial if you've got 2^64 addresses to choose from. This implies that either you should instead take the /64 prefix and apply the rate limit to that, or employ a more complex tiered scheme where both the /128 IP is limited, as well as its /64 and probably the /56 and /48 containing subnets as well, albeit with laxer limits given that there might plausibly be multiple users in those.

In the case of a hierarchical rate limit, there's also some ambiguity regarding whether an upper-level (/48) rate limit being triggered should or shouldn't cause a lower-level (/64) one to be updated as well. For now I don't have a good answer either way.

-------
Endnote
-------

Laravel (and by extension Lumen) `implements <https://github.com/laravel/framework/blob/6c0ffe3b274aeff16661efc33921ae5211b5f7d2/src/Illuminate/Cache/RateLimiter.php#L112-L129>`_ a variant of the "simple counter" algorithm, though it's not synchronized and has a small window in which a race condition can happen.

On the other hand, though Larvel's documentation blatantly suggests limiting directly by request IP if no user ID is available, the upside is that the limiting key is `freely configurable per named limiter <https://laravel.com/docs/9.x/routing#segmenting-rate-limits>`_ and it's possible to combine `multiple rate limits <https://laravel.com/docs/9.x/routing#multiple-rate-limits>`_ which allows implementing arbitrary logic to implement the suggestions listed herein.
