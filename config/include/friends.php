<?php header("http/1.0 403 Verboden") ?>
<h3>friends.php</h3>
<p>Toegang tot dit bestand via HTTP is verboden.
<?php exit() ?>

<pre>;
;
; *****************************************************************************
;
; include/friends.php
;
; ADOBE SYSTEMS INCORPORATED
; Copyright 2000-2002 Adobe Systems Incorporated. All Rights Reserved.
;
; NOTICE:  Notwithstanding the terms of the Adobe GoLive End User
; License Agreement, Adobe permits you to reproduce and distribute this
; file only as an integrated part of a web site created with Adobe
; GoLive software and only for the purpose of enabling your client to
; display their web site. All other terms of the Adobe license
; agreement remain in effect.
; -----------------------------------------------------------------------------
;
; GoLive gebruikt IP-beveiliging om toegang tot de gegevensbronnen in een site
; te beveiligen. Alle bestanden in de mappen info/ en actions/ado/ retourneren
; een fout 403 Verboden, tenzij het IP-adres van een gebruiker overeenkomt met
; één van de adressen in dit bestand.
;
; De ingangen staan op een aparte regel en bevatten ofwel het IP-adres van één
; systeem of een netwerkadres gevolgd door een netmasker. Voorbeelden:
;
;	 127.0.0.1					   toegang verlenen tot het lokale systeem
;	 151.32.156.78				   toegang verlenen tot systeem 151.32.156.78
;	 151.32.156.0  255.255.255.0   toegang verlenen tot systemen in het netwerk 151,320,156.0
;	 10.0.0.0	255.0.0.0		   toegang verlenen tot systemen in het netwerk 10.0.0.0
;	 0.0.0.0   0.0.0.0			   alle beveiliging uitschakelen
;
; U bepaalt het IP- of netwerkadres van een systeem door onder Windows 98
; winipcfg of onder Windows NT ipconfig uit te voeren, of door op een Macintosh
; in het TCP/IP-regelpaneel te kijken. Als u toegang wilt verlenen aan een
; systeem waarop het IP-adres dynamisch wordt toegewezen, wijzigt u het
; bestand friends.php en uploadt u het telkens wanneer u een nieuw adres
; ontvangt. U kunt ook een netwerkadres en netmasker gebruiken die alle
; mogelijke dynamische adressen omvatten. Vergeet echter niet dat dan
; iedereen in die reeks adressen toegang krijgt.
;
; Dit bestand werkt niet goed met Macintosh-regeleinden. Als u het in GoLive
; bewerkt, moet u het webdatabaseteken voor regeleinden instellen op Windows
; (RT/RI).

212.45.36.31

;</pre>
>
