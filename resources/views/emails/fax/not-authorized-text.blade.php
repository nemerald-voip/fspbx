{{-- email-template
format: text
layout: none
--}}
Your fax message to {{ $attributes['fax_destination'] }} has failed.

The email address below is not authorized to send faxes. Please contact your system administrator.

Email: {{ $attributes['from'] }}
