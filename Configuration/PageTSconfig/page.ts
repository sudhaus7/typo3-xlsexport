mod.tx_sudhaus7xlsexport {
    settings {
        exports {
            tt_address {
                label = Adressen
                check (
                     select count(uid) from tt_address
            where pid=%d and deleted=0 and hidden=0
                )
                list (
                    select uid,first_name,last_name from tt_address where pid=%d and deleted=0 and hidden=0
                )
                export (
                    select uid,first_name,middle_name,last_name,address,building,room,city,zip,region,country,phone,fax,email,www,title,company  from tt_address where pid=%d and deleted=0 and hidden=0
                )
                # ignored
                archive =
                table = tt_address
                exportfields {
                    10 = uid
                    20 = first_name
                    30 = middle_name
                    40 = last_name
                    50 = address
                    60 = building
                    70 = room
                    80 = city
                    90 = zip
                    100 = region
                    110 = country
                    120 = phone
                    130 = fax
                    140 = email
                    150 = www
                    160 = title
                    170 = company
                }

                exportfieldnames {
                    10 = lfd. Nummer
                    20 = Vorname
                    30 = Mittelname
                    40 = Nachname
                    50 = Adresse
                    60 = Gebäude
                    70 = Raum
                    80 = Stadt
                    90 = PLZ
                    100 = Region
                    110 = Land
                    120 = Telefon
                    130 = Fax
                    140 = E-Mail
                    150 = Web
                    160 = Titel
                    170 = Firma
                }
            }
        }
    }
}
