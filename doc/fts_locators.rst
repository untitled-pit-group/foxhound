Note on the fulltext index locator format:

- The indexed fulltext is considered in terms of UTF-8 encoded bytes, whatever
  the representation of the text is in database. (This is a tradeoff between
  allowing random access based on the timestamp data and being agnostic towards
  the encoding; for simplicity's sake, I'm choosing the latter for now.)
- The locator index is simply a sorted list of pairs ``(u64 byte_position, u32
  locator_value)``. The byte position indexes into the fulltext; the locator
  value's interpretation depends on the media type.
  - For plaintext media, the locator index should be empty given that no
    locators are applicable in that case. A plaintext document inherently
    doesn't have any higher-level locators than the byte positions themselves.
  - For paginated media, the locator value denotes the page where the text in
    question is found.
  - For timed media, the locator value is the integer number of seconds since
    the start of the item where the text in question is spoken or otherwise
    enunciated. This depends on the media having monotonic timestamps, but this
    assumption is enforced at the first decode step, even though the semantics
    in the opposite case aren't well-defined.

To find the locator associated with a given byte position, perform a binary
search among the locator index; this should not cause problems given that the
indexing strategy is simple. The inverse (finding the byte position at which
a particular locator begins) is trivial.
