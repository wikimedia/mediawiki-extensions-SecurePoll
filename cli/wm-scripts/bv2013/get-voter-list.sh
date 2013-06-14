#!/bin/bash

for wiki in `</a/common/all.dblist`; do
	echo $wiki;
	echo "SELECT '$wiki', user_name,user_email,up_value FROM securepoll_lists JOIN user ON li_member=user_id JOIN user_properties ON up_property='language' AND up_user=user_id WHERE li_name='board-vote-2011' AND user_email_authenticated IS NOT NULL;" \
		| sql $wiki | tail -n +2 >> /a/common/elections-2013-spam/users-by-wiki;
done
