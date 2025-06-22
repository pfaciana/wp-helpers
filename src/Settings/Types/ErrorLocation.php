<?php

namespace RWP\Settings\Types;

enum ErrorLocation: string
{
	case ADMIN_NOTICES = 'admin_notices';
	case FORM = 'form';
	case SECTION = 'section';
	case FIELD = 'field';
}
