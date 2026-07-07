{{-- version: 1.1.4 --}}

@switch($flavor)

{{-- ================= Grandstream GXP17xx cfgmac.xml ================= --}}
@case('cfgmac.xml')
@php
    /*
     * Firmware 1.0.1.133 vendor baseline for GXP1782/1780/1760.
     * FS PBX settings, accounts, and programmable keys override these values below.
     */
    $vendorDefaults = <<<'PVALUES'
##########################################################################################
## Configuration Template For GXP1782/1780/1760 Firmware Version 1.0.1.133              ##
##########################################################################################

####################################################################
# Account Settings                                                ##
####################################################################

####################################################################
# Account 1                                                       ##
####################################################################
####################################################################
# Account 1/General Settings
####################################################################
# Account Active. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P271 = 1

# Account Name
# String
P270 =

# SIP Server
# String
P47 =

# Secondary SIP Server
# String
P2312 =

# Outbound Proxy
# String
P48 =

# Backup Outbound Proxy
# String
P2333 =

# BLF Server
# String
P2375 =

# SIP User ID
# String
P35 =

# Authenticate ID
# String
P36 =

# Authenticate Password
# String
P34 =

# Name
# String
P3 =

# Voice Mail Access Number
# String
P33 =

# Account Display
# Number: 0 - User Name, 1 - User Identification. Default is 0.
# Mandatory
P2380 = 0

###############################################################
# Account 1/Network Settings
###############################################################
# DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P103 = 0

# Primary IP. Maximum 15 characters
# String
P2308 =

# Backup IP 1
# String
P2309 =

# Backup IP 2
# String
P2310 =

# NAT Traversal. 0 - No, 1 - STUN, 2 - keep alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0.
# Number: 0, 1, 2, 3, 4, 5
# Mandatory
P52 = 0

# Support Rport (RFC 3581). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P131 = 1

# Proxy-Require (A SIP extension to enable firewall penetration). Max length is 64 characters
# String
P197 =

# Use SBC. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26090 = 0

###############################################################
# Account 1/SIP Settings/Basic Settings
###############################################################
# TEL URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0
# Number: 0, 1, 2
# Mandatory
P63 = 0

# SIP Registration. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P31 = 1

# Unregister On Reboot. 0 - No, 1 - Yes, 2 - Instance. Default is 0
# Number: 0, 1
# Mandatory
P81 = 0

# Register Expiration (in minutes). Default is 60. Max about 45 days
# Number: 1 - 64800
# Mandatory
P32 = 60

# Subscribe Expiration. Default is 60.
# Number: 1-64800
# Mandatory
P26051 = 60

# Register Before Expiration (in seconds). Default is 0 second
# Number: 0 - 64800
# Mandatory
P2330 = 0

# Enable OPTIONS Keep Alive. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2397 = 0

# OPTIONS Keep Alive Interval. Default is 30.
# Number
# Mandatory
P2398 = 30

# OPTIONS Keep Alive Max Lost. Default is 3.
# Number
# Mandatory
P2399 = 3

# Local SIP Port. Default is 5060
# Number
# Mandatory
P40 = 5060

# SIP Registration Failure Retry Wait Time (in seconds). Default is 20
# Number: 1 - 3600
# Mandatory
P138 = 20

# SIP T1 Timeout. RFC 3261 T1 value (RTT estimate)
# 50 - 0.5 sec, 100 - 1 sec, 200 - 2 sec. Default is 50
# Number: 50, 100, 200
# Mandatory
P209 = 50

# SIP T2 Timeout. RFC 3261 T2 value. The maximum retransmit interval for non-INVITE requests and INVITE responses
# 200 - 2 sec, 400 - 4 sec, 800 - 8 sec. Default is 400
# Number: 200, 400, 800
# Mandatory
P250 = 400

# SIP Transport. 0 - UDP, 1 - TCP, 2 - TLS/TCP. Default is 0
# Number: 0, 1, 2
# Mandatory
P130 = 0

# SIP URI Scheme when using TLS. 0 - sip, 1 - sips. Default is 1
# Number: 0, 1
# Mandatory
P2329 = 1

# Use Actual Ephemeral Port in Contact with TCP/TLS. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2331 = 0

# Outbound Proxy Mode. 0 - in route, 1 - not in route, 2 - always send to. Default is 0
# Number: 0, 1, 2
# Mandatory
P2305 = 0

# Support SIP Instance ID. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P288 = 1

# SUBSCRIBE for MWI. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P99 = 0

# SUBSCRIBE for Registration. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2319 = 0

# Enable 100rel. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P272 = 0

# Callee ID Display. 0 - Auto, 1 - Disabled, 2 - To Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P26025 = 0

# Caller ID Display. 0 - Auto, 1 - Disabled, 2 - From Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P2324 = 0

# Ignore Alert-Info header
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26018 = 0

# Add Auth Header On Initial REGISTER. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2359 = 0

###############################################################
# Account 1/SIP Settings/Custom SIP Headers
###############################################################
# Use Privacy Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2338 = 0

# Use P-Preferred-Identity Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2339 = 0

# Use X-Grandstream-PBX Header. Default is 1.
# Number 0, 1
# Mandatory
P26054 = 1

# Use P-Access-Network-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26058 = 1

# Use P-Emergency-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26059 = 1

# Use MAC Header
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P26061 = 0

###############################################################
# Account 1/SIP Settings/Advanced Features
###############################################################
# Line-Seize Timeout (in seconds). Default is 15
# Number: 15 - 60
# Mandatory
P2313 = 15

# Eventlist BLF URI
# String
P134 =

# Auto Provision Eventlist BLFs. 0-Disabled, 1-Enabled. Default is 0
# Number:0,1
# Mandatory
P2389 = 0

# Conference URI
# String
P2318 =

# Music On Hold URI
# String
P2350 =

# Force BLF Call-pickup by prefix. 0 - Disable, 1 - Enable. Default is 0
# Number: 0, 1
# Mandatory
P6752 = 0

# BLF Call-pickup Prefix. Default is **
# String
# Mandatory
P1347 = **

# Call Pickup Barge-In Code
# String
P26046 =

# PUBLISH for Presence. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P188 = 0

# Omit charset=UTF-8 in MESSAGE. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P2355 = 0

# Special Feature. 100 - Standard, 101 - Nortel MCS, 102- Broadsoft, 108 - CBCOM,
# 109 - RNK, 110 - Sylantro, 117 - Huawei IMS, 119 - Phonepower
# Default is 100
# Number: 100, 101, 102, 108, 109, 110, 117, 119
# Mandatory
P198 = 100

# Broadsoft
# Broadsoft Call Center. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2341 = 0

# Hoteling Event. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2342 = 0

# Call Center Status. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2343 = 0

# Feature Key Synchronization. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P2325 = 0

# Broadsoft Call Park. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P2388 = 0

###############################################################
# Account 1/SIP Settings/Session Timer
###############################################################
# Enable Session Timer. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2395 = 1

# Session Expiration (in seconds). Default is 180
# Number: 90 - 64800
# Mandatory
P260 = 180

# Minimum SE (in seconds). Default is 90. This value must be lower than or equal to P260
# Number: 90 - 64800
# Mandatory
P261 = 90

# Caller Request Timer (Request for timer when calling). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P262 = 0

# Callee Request Timer (Request for timer when called. i.e. if remote party supports timer but did not request for one)
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P263 = 0

# Force Timer (Still use timer when remote party does not support timer). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P264 = 0

# UAC Specify Refresher. 0 - omit, 1 - UAC, 2 - UAS. Default is 0
# Number: 0, 1, 2
# Mandatory
P266 = 0

# UAS Specify Refresher. 1 - UAC, 2 - UAS. Default is 1
# Number: 1, 2
# Mandatory
P267 = 1

# Force INVITE (Always refresh with INVITE instead of UPDATE even when remote party supports UPDATE).
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P265 = 0

###############################################################
# Account 1/SIP Settings/Security Settings
###############################################################
# Check Domain Certificates. When set to Yes/Enabled, domain certificate will be checked as defined in RFC5922
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2311 = 0

# Validate Certification Chain. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2367 = 0

# Validate Incoming Messages. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2306 = 0

# Check SIP User ID for incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P258 = 0

# Accept Incoming SIP from Proxy Only. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2347 = 0

# Authenticate Incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2346 = 0

###############################################################
# Account 1/Audio Settings
###############################################################
# Preferred Vocoder
# 0 - PCMU, 2 - G.726-32, 8 - PCMA, 9 - G.722, 18 - G.729A/B, 4 - G.723.1, 98 - iLBC, 125 - OPUS
# choice 1. Default is 0
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P57 = 0

# choice 2. Default is 8
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P58 = 8

# choice 3. Default is 4
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P59 = 4

# choice 4. Default is 18
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P60 = 18

# choice 5. Default is 9
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P61 = 9

# choice 6. Default is 98
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P62 = 98

# choice 7. Default is 2
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P46 = 2

# choice 8. Default is 125
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P98 = 125

# Use First Matching Vocoder in 200OK SDP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2348 = 0

# Disable Multiple m line in SDP
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P137 = 0

# SRTP Mode. 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 3 - Optional. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P183 = 0

# Crypto Life Time
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2363 = 0

# Symmetric RTP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P291 = 0

# Silence Suppression. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P50 = 0

# Jitter Buffer Type. 0 - Fixed, 1 - Adaptive. Default is 1
# Number: 0, 1
# Mandatory
P133 = 1

# Jitter Buffer Length. 0 - 100ms, 1 - 200ms, 2 - 300ms, 3 - 400ms, 4 - 500ms, 5 - 600ms, 6 - 700ms, 7 - 800ms
# Default is 2
# Number: 0, 1, 2, 3, 4, 5, 6, 7
# Mandatory
P132 = 2

# Voice Frames per TX (up to 10/20/32/64 frames for G711/G726/G723/other codecs respectively). Default is 2
# Number: 1 - 64
# Mandatory
P37 = 2

# G723 Rate. 0 - 6.3kbps encoding rate, 1 - 5.3kbps encoding rate. Default is 1
# Number: 0, 1
# Mandatory
P49 = 1

# G.726-32 Packing Mode. 0 - ITU, 1 - IETF. Default is 0
# Number: 0, 1
# Mandatory
P2323 = 0

# iLBC Frame Size. 0 - 20ms, 1 - 30ms. Default is 1
# Number: 0, 1
# Mandatory
P97 = 1

# iLBC Payload Type. Default is 97
# Number: 96 - 127
# Mandatory
P96 = 97

# OPUS Payload Type. Default is 123
# Number: 96 - 127
# Mandatory
P2385 = 123

# DTMF Payload Type. Default is 101
# Number: 96 - 127
# Mandatory
P79 = 101

# Send DTMF: In-audio. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2301 = 0

# Send DTMF: Via RTP (RFC2833). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2302 = 1

# Send DTMF: Via SIP INFO. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2303 = 0

###############################################################
# Account 1/Call Settings
###############################################################
# Early Dial (use "Yes" only if proxy supports 484 response). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P29 = 0

# Dial Plan Prefix
# String
P66 =

# Dial Plan. Default value is { x+ | \+x+ | *x+ | *xx*x+ }
# String
# Mandatory
P290 = { x+ | \+x+ | *x+ | *xx*x+ }

# Bypass Dial Plan
# String: contact,incoming,outgoing,dialing,mpk,api. Default is mpk
P2382 = mpk

# Call Log. 0 - Log All Calls, 1 - Log Incoming/Outgoing only (missed calls NOT recorded), 2 - Disable Call Log. Default is 0
# Number: 0, 1, 2
# Mandatory
P182 = 0

# Send Anonymous (caller ID will be blocked if set to Yes). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P65 = 0

# Anonymous Call Rejection. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P129 = 0

# Auto Answer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P90 = 0

# Allow Auto Answer by Call-Info. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P298 = 0

# Custom Call-Info for Auto Answer.
# String
P2356 =

# Refer-To Use Target Contact. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P135 = 0

# Transfer on Conference HangUp. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2304 = 0

# Disable Recovery on Blind Transfer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2384 = 0

# No Key Entry Timeout (in seconds). Default is 4
# Number: 1 - 15
# Mandatory
P85 = 4

# Use # as Dial Key. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P72 = 1

# On Hold Reminder Tone. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P26006 = 0

#Ringtone
# Account Ring Tone.
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P104 = 0

# Matching Incoming Caller ID. Matching Rule 1
# String
P1488 =

# Matching Rule 1 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1489 = 0

# Matching Incoming Caller ID. Matching Rule 2
# String
P1490 =

# Matching Rule 2 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1491 = 0

# Matching Incoming Caller ID. Matching Rule 3
# String
P1492 =

# Matching Rule 3 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1493 = 0

# Matching Incoming Caller ID. Matching Rule 4
# String
P6716 =

# Matching Rule 4 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6717 = 0

# Matching Incoming Caller ID. Matching Rule 5
# String
P6718 =

# Matching Rule 5 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6719 = 0

# Matching Incoming Caller ID. Matching Rule 6
# String
P6720 =

# Matching Rule 6 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6721 = 0

# Matching Incoming Caller ID. Matching Rule 7
# String
P26064 =

# Matching Rule 7 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26065 = 0

# Matching Incoming Caller ID. Matching Rule 8
# String
P26066 =

# Matching Rule 8 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26067 = 0

# Matching Incoming Caller ID. Matching Rule 9
# String
P26068 =

# Matching Rule 9 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26069 = 0

# Matching Incoming Caller ID. Matching Rule 10
# String
P26096 =

# Matching Rule 10 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26097 = 0

# Ring Timeout (in seconds). Default is 60
# Number: 30 - 3600
# Mandatory
P1328 = 60

###############################################################
# Account 1/Feature Codes
###############################################################
# Enable Local Call Features.  0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P191 = 1

# Do Not Disturb (DND) - On
# String
P2344 =

# Do Not Disturb (DND) - Off
# String
P2345 =

# Delayed Call Forward Wait Time (in seconds). Default is 20
# Number: 1 - 120
# Mandatory
P139 = 20

####################################################################
# Account 2
####################################################################
###############################################################
# Account 2/General Settings
###############################################################
# Account Active. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P401 = 1

# Account Name
# String
P417 =

# SIP Server
# String
P402 =

# Secondary SIP Server
# String
P2412 =

# Outbound Proxy
# String
P403 =

# Backup Outbound Proxy
# String
P2433 =

# BLF Server
# String
P2475 =

# SIP User ID
# String
P404 =

# Authenticate ID
# String
P405 =

# Authenticate password
# String
P406 =

# Name
# String
P407 =

# Voice Mail UserID
# String
P426 =

# Account Display
# Number: 0 - User Name, 1 - User Identification. Default is 0.
# Mandatory
P2480 = 0

###############################################################
# Account 2/Network Settings
###############################################################
# DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P408 = 0

# Primary IP. Maximum 15 characters
# String
P2408 =

# Backup IP 1
# String
P2409 =

# Backup IP 2
# String
P2410 =

# NAT Traversal. 0 - No, 1 - STUN, 2 - keep alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0
# Number: 0, 1, 2, 3, 4, 5
# Mandatory
P414 = 0

# Support Rport (RFC 3581). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P447 = 1

# Proxy-Require (A SIP extension to enable firewall penetration). Max length is 64 characters
# String
P418 =

# Use SBC. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26190 = 0

###############################################################
# Account 2/SIP Settings/Basic Settings
###############################################################
# TEL URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0
# Number: 0, 1, 2
# Mandatory
P409 = 0

# SIP Registration. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P410 = 1

# Unregister On Reboot. 0 - No, 1 - Yes, 2 - Instance. Default is 0
# Number: 0, 1
# Mandatory
P411 = 0

# Register Expiration (in minutes). Default is 60. Max is 45 days
# Number: 1 - 64800
# Mandatory
P412 = 60

# Subscribe Expiration. Default is 60.
# Number: 1-64800
# Mandatory
P26151 = 60

# Reregister before Expiration (in seconds). Default is 0
# Number: 0 - 64800
# Mandatory
P2430 = 0

# Enable OPTIONS Keep Alive. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2497 = 0

# OPTIONS Keep Alive Interval. Default is 30.
# Number
# Mandatory
P2498 = 30

# OPTIONS Keep Alive Max Lost. Default is 3.
# Number
# Mandatory
P2499 = 3

# Local SIP port. Default is 5062
# Number
# Mandatory
P413 = 5062

# SIP Registration Failure Retry Wait Time (in seconds). Default is 20
# Number: 1 - 3600
# Mandatory
P471 = 20

# SIP T1 Timeout. RFC 3261 T1 value (RTT estimate)
# 50 - 0.5 sec, 100 - 1 sec, 200 - 2 sec. Default is 100
# Number: 50, 100, 200
# Mandatory
P440 = 50

# SIP T2 Timeout. RFC 3261 T2 value. The maximum retransmit interval for non-INVITE requests and INVITE responses
# 200 - 2 sec, 400 - 4 sec, 800 - 8 sec. Default is 400
# Number: 200, 400, 800
# Mandatory
P441 = 400

# SIP Transport. 0 - UDP, 1 - TCP, 2 - TCP/TLS. Default is 0
# Number: 0, 1, 2
# Mandatory
P448 = 0

# SIP URI Scheme when using TLS. 0 - sip, 1 - sips. Default is 1
# Number: 0, 1
# Mandatory
P2429 = 1

# Use Actual Ephemeral Port in Contact with TCP/TLS. 0 - No, 1- Yes. Default is 0
# Number: 0, 1
# Mandatory
P2431 = 0

# Outbound Proxy Mode. 0 - in route, 1 - not in route, 2 - always send to. Default is 0
# Number: 0, 1, 2
# Mandatory
P2405 = 0

# Support SIP Instance ID. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P489 = 1

# SUBSCRIBE for MWI (Whether or not send SUBSCRIBE for Message Waiting Indication). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P415 = 0

# SUBSCRIBE for Registration. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2419 = 0

# Enable 100rel. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P435 = 0

# Callee ID Display. 0 - Auto, 1 - Disabled, 2 - To Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P26125 = 0

# Caller ID Display. 0 - Auto, 1 - Disabled, 2 - From Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P2424 = 0

# Ignore Alert-Info header
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26118 = 0

# Add Auth Header On Initial REGISTER. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2459 = 0

###############################################################
# Account 2/SIP Settings/Custom SIP Headers
###############################################################
# Use Privacy Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2438 = 0

# Use P-Preferred-Identity Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2439 = 0

# Use X-Grandstream-PBX Header. Default is 1.
# Number 0, 1
# Mandatory
P26154 = 1

# Use P-Access-Network-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26158 = 1

# Use P-Emergency-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26159 = 1

# Use MAC Header
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P26161 = 0

###############################################################
# Account 2/SIP Settings/Advanced Features
###############################################################
# Line-Seize Timeout (in seconds). Default is 15
# Number: 15 - 60
# Mandatory
P2413 = 15

# Eventlist BLF URI
# String
P444 =

#Auto Provision Eventlist BLFs. 0-Disabled, 1-Enabled. Default is 0
#Number:0,1
#Mandatory
P2489 = 0

# Conference URI
# String
P2418 =

# Music On Hold URI
# String
P2450 =

# Force BLF Call-pickup by prefix. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P6753 = 0

# BLF Call-pickup Prefix. Default is **
# String
# Mandatory
P481 = **

# Call Pickup Barge-In Code
# String
P26146 =

# PUBLISH for Presence. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P488 = 0

# Omit charset=UTF-8 in MESSAGE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2455 = 0

# Special Feature. 100 - Standard, 101 - Nortel MCS, 102- Broadsoft, 108 - CBCOM, 109 - RNK, 110 - Sylantro, 117 - Huawei IMS
# Number: 100, 101, 102, 108, 109, 110, 117. Default is 100
# Mandatory
P424 = 100

# Broadsoft
# Broadsoft Call Center. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2441 = 0

# Hoteling Event. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2442 = 0

# Call Center Status. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2443 = 0

# Feature Key Synchronization. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P2425 = 0

# Broadsoft Call Park. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P2488 = 0
###############################################################
# Account 2/SIP Settings/Session Timer
###############################################################
# Enable Session Timer. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2495 = 1

# Session Expiration (in seconds). Default is 180
# Number: 90 - 64800
# Mandatory
P434 = 180

# Minimum SE (in seconds). Default is 90. This value must be lower than or equal to P434
# Number: 90 - 64800
# Mandatory
P427 = 90

# Caller Request Timer (Request for timer when calling). 0 - No, 1 - Yes
# Number: 0, 1
# Mandatory
P428 = 0

# Callee Request Timer (Request for timer when called. i.e. if remote party supports timer but did not request for one)
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P429 = 0

# Force Timer (Still use timer when remote party does not support timer). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P430 = 0

# UAC Specify Refresher. 0 - omit, 1 - UAC, 2 - UAS. Default is 0
# Number: 0, 1, 2
# Mandatory
P432 = 0

# UAS Specify Refresher. 1 - UAC, 2 - UAS. Default is 1
# Number: 1, 2
# Mandatory
P433 = 1

# Force INVITE (Always refresh with INVITE instead of UPDATE even when remote party supports UPDATE)
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P431 = 0

###############################################################
# Account 2/SIP Settings/Security Settings
###############################################################
# Check Domain Certificates. 0 - No, 1 - Yes. When set to Yes/Enabled, the domain certificate will be checked as defined in RFC5922
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2411 = 0

# Validate Certification Chain. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2467 = 0

# Validate Incoming Messages. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2406 = 0

# Check SIP User ID for incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P449 = 0

# Accept Incoming SIP from Proxy Only. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2447 = 0

# Authenticate Incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2446 = 0

###############################################################
# Account 2/Audio Settings
###############################################################
# Preferred Vocoder
# 0 - PCMU, 2 - G.726-32, 8 - PCMA, 9 - G.722, 18 - G.729A/B, 4 - G.723.1, 98 - iLBC, 125 - OPUS
# choice 1. Default is 0
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P451 = 0

# choice 2. Default is 8
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P452 = 8

# choice 3. Default is 4
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P453 = 4

# choice 4. Default is 18
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P454 = 18

# choice 5. Default is 9
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P455 = 9

# choice 6. Default is 98
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P456 = 98

# choice 7. Default is 2
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P457 = 2

# choice 8. Default is 125
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P458 = 125

# Use First Matching Vocoder in 200OK SDP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2448 = 0

# Disable Multiple m line in SDP
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P487 = 0

# SRTP Mode
# 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 3 - Optional. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P443 = 0

#Crypto Life Time
#0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2463 = 0

# Symmetric RTP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P460 = 0

# Silence Suppression 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P485 = 0

# Jitter Buffer Type. 0 - Fixed, 1 - Adaptive. Default is 1
# Number: 0, 1
# Mandatory
P498 = 1

# Jitter Buffer Length.
# 0 - 100ms, 1 - 200ms, 2 - 300ms, 3 - 400ms, 4 - 500ms, 5 - 600ms, 6 - 700ms, 7 - 800ms. Default is 2
# Number: 0, 1, 2, 3, 4, 5, 6, 7
# Mandatory
P497 = 2

# Voice Frames per TX (up to 10/20/32/64 frames for G711/G726/G723/other codecs respectively). Default is 2
# Number: 1 - 64
# Mandatory
P486 = 2

# G723 Rate. 0 - 6.3kbps encoding rate, 1 - 5.3kbps encoding rate. Default is 1
# Number: 0, 1
# Mandatory
P493 = 1

# G.726-32 Packing Mode. 0 - ITU, 1 - IETF. default is 0
# Number: 0, 1
# Mandatory
P2423 = 0

# iLBC Frame Size. 0 - 20ms, 1 - 30ms. Default is 1
# Number: 0, 1
# Mandatory
P495 = 1

# iLBC Payload Type. Default is 97
# Number: 96 - 127
# Mandatory
P494 = 97

# OPUS Payload Type. Default is 123
# Number: 96 - 127
# Mandatory
P2485 = 123

# DTMF Payload Type. Default is 101
# Number: 96 - 127
# Mandatory
P496 = 101

# Send DTMF: In-audio. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2401 = 0

# Send DTMF: Via RTP (RFC2833). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2402 = 1

# Send DTMF: Via SIP INFO. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2403 = 0
###############################################################
# Account 2/Call Settings
###############################################################
# Early Dial (use "Yes" only if proxy supports 484 response). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P422 = 0

# Dial Plan Prefix
# String
P419 =

# Dial Plan. Default is { x+ | \+x+ | *x+ | *xx*x+ }
# String
# Mandatory
P459= { x+ | \+x+ | *x+ | *xx*x+ }

# Bypass Dial Plan
# String: contact,incoming,outgoing,dialing,mpk,api. Default is mpk
P2482 = mpk

# Call Log. 0 - Log All Calls, 1 - Log Incoming/Outgoing only (missed calls NOT recorded), 2 - Disable Call Log. Default is 0
# Number: 0, 1, 2
# Mandatory
P442 = 0

# Send Anonymous (caller ID will be blocked if set to Yes). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P421 = 0

# Anonymous Call Rejection. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P446 = 0

# Auto Answer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P425 = 0

# Allow Auto Answer by Call-Info. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P438 = 0

# Custom Call-Info for Auto Answer.
# String
P2456 =

# Refer-To Use Target Contact. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P469 = 0

# Transfer on conference HangUp. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2404 = 0

# Disable Recovery on Blind Transfer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2484 = 0

# No Key Entry Timeout. Default is 4
# Number: 1 - 15
# Mandatory
P491 = 4

# Use # as Dial Key. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P492 = 1

# On Hold Reminder Tone. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P26106 = 0

# Ringtone
# Account Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P423 = 0

# Matching Incoming Caller ID. Matching Rule 1
# String
P1494 =

# Matching Rule 1 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1495 = 0

# Matching Incoming Caller ID. Matching Rule 2.
# String
P1496 =

# Matching Rule 2 Distinctive Ringtone.
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1497 = 0

# Matching Incoming Caller ID. Matching Rule 3.
# String
P1498 =

# Matching Rule 3 Distinctive Ringtone.
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1499 = 0

# Matching Incoming Caller ID. Matching Rule 4
# String
P6722 =

# Matching Rule 4 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6723 = 0

# Matching Incoming Caller ID. Matching Rule 5
# String
P6724 =

# Matching Rule 5 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6725 = 0

# Matching Incoming Caller ID. Matching Rule 6
# String
P6726 =

# Matching Rule 6 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6727 = 0

# Matching Incoming Caller ID. Matching Rule 7
# String
P26164 =

# Matching Rule 7 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26165 = 0

# Matching Incoming Caller ID. Matching Rule 8
# String
P26166 =

# Matching Rule 8 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26167= 0

# Matching Incoming Caller ID. Matching Rule 9
# String
P26168 =

# Matching Rule 9 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26169 = 0

# Matching Incoming Caller ID. Matching Rule 10
# String
P26196 =

# Matching Rule 10 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26197 = 0

# Ring Timeout (in seconds) Default is 60
# Number: 30 - 3600
# Mandatory
P476 = 60

###############################################################
# Account 2/Feature Codes
###############################################################
# Enable Local Call Features.  0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P420 = 1

# Do Not Disturb (DND) - On
# String
P2444 =

# Do Not Disturb (DND) - Off
# String
P2445 =

# Delayed Call Forward Wait Time (in seconds). Default is 20
# Number: 1 - 120
# Mandatory
P470 = 20
####################################################################
# Account 3
####################################################################
###############################################################
# Account 3/General Settings
###############################################################
# Account Active. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P501 = 1

# Account Name
# String
P517 =

# SIP Server
# String
P502 =

# Secondary SIP Server
# String
P2512 =

# Outbound Proxy
# String
P503 =

# Backup Outbound Proxy
# String
P2533 =

# BLF Server
# String
P2575 =

# SIP User ID
# String
P504 =

# Authenticate ID
# String
P505 =

# Authenticate Password
# String
P506 =

# Name
# String
P507 =

# Voice Mail UserID
# String
P526 =

# Account Display
# Number: 0 - User Name, 1 - User Identification. Default is 0.
# Mandatory
P2580 = 0

###############################################################
# Account 3/Network Settings
###############################################################
# DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P508 = 0

# Primary IP. Maximum 15 characters
# String
P2508 =

# Backup IP 1
# String
P2509 =

# Backup IP 2
# String
P2510 =

# NAT Traversal. 0 - No, 1 - STUN, 2 - keep alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0
# Number: 0, 1, 2, 3, 4, 5
# Mandatory
P514 = 0

# Support Rport (RFC 3581). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P547 = 1

# Proxy-Require (A SIP extension to enable firewall penetration). Max length is 64 characters
# String
P518 =

# Use SBC. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26290 = 0

###############################################################
# Account 3/SIP Settings/Basic Settings
###############################################################
# TEL URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0
# Number: 0, 1, 2
# Mandatory
P509 = 0

# SIP Registration. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P510 = 1

# Unregister On Reboot. 0 - No, 1 - Yes, 2 - Instance. Default is 0
# Number: 0, 1
# Mandatory
P511 = 0

# Register Expiration (in minutes). Default is 60. Max is 45 days
# Number: 1 - 64800
# Mandatory
P512 = 60

# Subscribe Expiration. Default is 60.
# Number: 1-64800
# Mandatory
P26251 = 60

# Reregister before Expiration (in seconds). Default is 0 second
# Number: 0 - 64800
# Mandatory
P2530 = 0

# Enable OPTIONS Keep Alive. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2597 = 0

# OPTIONS Keep Alive Interval. Default is 30.
# Number
# Mandatory
P2598 = 30

# OPTIONS Keep Alive Max Lost. Default is 3.
# Number
# Mandatory
P2599 = 3

# Local SIP Port. Default is 5064
# Number
# Mandatory
P513 = 5064

# SIP Registration Failure Retry Wait Time (in seconds). Default is 20
# Number: 1 - 3600
# Mandatory
P571 = 20

# SIP T1 Timeout. RFC 3261 T1 value (RTT estimate)
# 50 - 0.5 sec, 100 - 1 sec, 200 - 2 sec. Default is 100
# Number: 50, 100, 200
# Mandatory
P540 = 50

# SIP T2 Timeout. RFC 3261 T2 value. The maximum retransmit interval for non-INVITE requests and INVITE responses.
# 200 - 2 sec, 400 - 4 sec, 800 - 8 sec. Default is 400
# Number: 200, 400, 800
# Mandatory
P541 = 400

# SIP Transport. 0 - UDP, 1 - TCP. 2- TCP/TLS. Default is 0
# Number: 0, 1, 2
# Mandatory
P548 = 0

# SIP URI Scheme When Using TLS. 0 - sip, 1 - sips. Default is 1
# Number: 0, 1
# Mandatory
P2529 = 1

# Use Actual Ephemeral Port in Contact with TCP/TLS. 0 - No, 1- Yes. Default is 0
# Number: 0, 1
# Mandatory
P2531 = 0

# Outbound Proxy Mode. 0 - in route, 1 - not in route, 2 - always send to. Default is 0
# Number: 0, 1, 2
# Mandatory
P2505 = 0

# Support SIP Instace ID. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P589 = 1

# SUBSCRIBE for MWI (Whether or not send SUBSCRIBE for Message Waiting Indication). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P515 = 0

# SUBSCRIBE for Registration. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2519 = 0

# Enable 100rel. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P535 = 0

# Callee ID Display. 0 - Auto, 1 - Disabled, 2 - To Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P26225= 0

# Caller ID Display. 0 - Auto, 1 - Disabled, 2 - From Header. Default is 0
# Number: 0, 1, 2
# Mandatory
P2524 = 0

# Ignore Alert-Info header
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26218 = 0

# Add Auth Header On Initial REGISTER. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2559 = 0

###############################################################
# Account 3/SIP Settings/Custom SIP Headers
###############################################################
# Use Privacy Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2538 = 0

# Use P-Preferred-Identity Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
P2539 = 0

# Use X-Grandstream-PBX Header. Default is 1.
# Number 0, 1
# Mandatory
P26254 = 1

# Use P-Access-Network-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26258 = 1

# Use P-Emergency-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
P26259 = 1

# Use MAC Header
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P26261 = 0

##########################################
# Account 3/SIP Settings/Advanced Features
##########################################
# Line-Seize Timeout (in seconds). Default is 15
# Number: 15 - 60
# Mandatory
P2513 = 15

# Eventlist BLF URI
# String
P544 =

# Auto Provision Eventlist BLFs. 0-Disabled, 1-Enabled. Default is 0
# Number:0,1
# Mandatory
P2589 = 0

# Conference URI
# String
P2518 =

# Music On Hold URI
# String
P2550 =

# Force BLF Call-pickup by prefix. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P6754 = 0

# BLF Call-pickup Prefix. Default is **
# String
# Mandatory
P581 = **

# Call Pickup Barge-In Code
# String
P26246 =

# PUBLISH for Presence. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P588 = 0

# Omit charset=UTF-8 in MESSAGE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2555 = 0

# Special Feature. 100 - Standard, 101 - Nortel MCS, 102- Broadsoft, 108 - CBCOM, 109 - RNK, 110 - Sylantro, 117 - Huawei IMS. Default is 100
# Number: 100, 101, 102, 108, 109, 110, 117
# Mandatory
P524 = 100

# Broadsoft
# Broadsoft Call Center. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2541 = 0

# Hoteling Event. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2542 = 0

# Call Center Status. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2543 = 0

# Feature Key Synchronization. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P2525 = 0

# Broadsoft Call Park. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P2588 = 0
##########################################
# Account 3/SIP Settings/Session Timer
##########################################
# Enable Session Timer. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2595 = 1

# Session Expiration (in seconds). Default is 180
# Number: 90 - 64800
# Mandatory
P534 = 180

# Minimum SE (in seconds). Default is 90. This value must be lower than or equal to P534
# Number: 90 - 64800
# Mandatory
P527 = 90

# Caller Request Timer (Request for timer when calling) 0 - No, 1 - Yes
# Number: 0, 1
# Mandatory
P528 = 0

# Callee Request Timer (Request for timer when called. i.e. if remote party supports timer but did not request for one)
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P529 = 0

# Force Timer (Still use timer when remote party does not support timer). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P530 = 0

# UAC Specify Refresher. 0 - omit, 1 - UAC, 2 - UAS. Default is 0
# Number: 0, 1, 2
# Mandatory
P532 = 0

# UAS Specify Refresher. 1 - UAC, 2 - UAS. Default is 1
# Number: 1, 2
# Mandatory
P533 = 1

# Force INVITE (Always refresh with INVITE instead of UPDATE even when remote party supports UPDATE). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P531 = 0
##########################################
# Account 3/SIP Settings/Security Settings
##########################################
# Check Domain Certificates. When set to Yes/Enabled, the domain certificate will be checked as defined in RFC5922
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2511 = 0

# Validate Certification Chain. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2567 = 0

# Validate Incoming Messages. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2506 = 0

# Check SIP User ID for incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P549 = 0

# Accept Incoming SIP from Proxy Only. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2547 = 0

# Authenticate Incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2546 = 0
##########################################
# Account 3/Audio Settings
##########################################
# Preferred Vocoder
# 0 - PCMU, 2 - G.726-32, 8 - PCMA, 9 - G.722, 18 - G.729A/B, 4 - G.723.1, 98 - iLBC, 125 - OPUS
# choice 1. Default is 0
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P551 = 0

# choice 2. Default is 8
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P552 = 8

# choice 3. Default is 4
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P553 = 4

# choice 4. Default is 18
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P554 = 18

# choice 5. Default is 9
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P555 = 9

# choice 6. Default is 98
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P556 = 98

# choice 7. Default is 2
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P557 = 2

# choice 8. Default is 125
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
P558 = 125

# Use First Matching Vocoder in 200OK SDP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2548 = 0



# Disable Multiple m line in SDP
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P587 = 0

# SRTP Mode
# 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 3 - Optional. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P543 = 0

# Crypto Life Time
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2563 = 0

# Symmetric RTP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P560 = 0

# Silence Suppression 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P585 = 0

# Jitter Buffer Type. 0 - Fixed, 1 - Adaptive. Default is 1
# Number: 0, 1
# Mandatory
P598 = 1

# Jitter Buffer Length. 0 - 100ms, 1 - 200ms, 2 - 300ms, 3 - 400ms, 4 - 500ms, 5 - 600ms, 6 - 700ms, 7 - 800ms. Default is 2
# Number: 0, 1, 2, 3, 4, 5, 6, 7
# Mandatory
P597 = 2

# Voice Frames per TX (up to 10/20/32/64 frames for G711/G726/G723/other codecs respectively). Default is 2
# Number: 1 - 64
# Mandatory
P586 = 2

# G723 Rate. 0 - 6.3kbps encoding rate, 1 - 5.3kbps encoding rate. Default is 1
# Number: 0, 1
# Mandatory
P593 = 1

# G.726-32 Packing Mode. 0 - ITU, 1 - IETF. Default is 0
# Number: 0, 1
# Mandatory
P2523 = 0

# iLBC Frame Size. 0 - 20ms, 1 - 30ms. Default is 1
# Number: 0, 1
# Mandatory
P595 = 1

# iLBC Payload Type. Default is 97
# Number: 96 - 127
# Mandatory
P594 = 97

# OPUS Payload Type. Default is 123
# Number: 96 - 127
# Mandatory
P2585 = 123

# DTMF Payload Type. Default is 101
# Number: 96 - 127
# Mandatory
P596 = 101

# Send DTMF: In-audio. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2501 = 0

# Send DTMF: Via RTP (RFC2833). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P2502 = 1

# Send DTMF: Via SIP INFO. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2503 = 0

##########################################
# Account 3/Call Settings
##########################################
# Early Dial (use "Yes" only if proxy supports 484 response). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P522 = 0

# Dial Plan Prefix
# String
P519 =

# Dial Plan. Default is { x+ | \+x+ | *x+ | *xx*x+ }
# String
# Mandatory
P559= { x+ | \+x+ | *x+ | *xx*x+ }

# Bypass Dial Plan
# String: contact,incoming,outgoing,dialing,mpk,api. Default is mpk
P2582 = mpk

# Call Log. 0 - Log All Calls, 1 - Log Incoming/Outgoing only (Missed calls NOT recorded), 2 - Disable Call Log. Default is 0
# Number: 0, 1, 2
# Mandatory
P542 = 0

# Account Ring Tone.
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P523 = 0

# Matching Incoming Caller ID. Matching Rule 1
# String
P1500 =

# Matching Rule 1 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1501 = 0

# Matching Incoming Caller ID. Matching Rule 2
# String
P1502 =

# Matching Rule 2 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1503 = 0

# Matching Incoming Caller ID. Matching Rule 3
# String
P1504 =

# Matching Rule 3 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P1505 = 0


# Matching Incoming Caller ID. Matching Rule 4
# String
P6728 =

# Matching Rule 4 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6729 = 0

# Matching Incoming Caller ID. Matching Rule 5
# String
P6730 =

# Matching Rule 5 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6731 = 0

# Matching Incoming Caller ID. Matching Rule 6
# String
P6732 =

# Matching Rule 6 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6733 = 0

# Matching Incoming Caller ID. Matching Rule 7
# String
P26264 =

# Matching Rule 7 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26265 = 0

# Matching Incoming Caller ID. Matching Rule 8
# String
P26266 =

# Matching Rule 8 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26267 = 0

# Matching Incoming Caller ID. Matching Rule 9
# String
P26268 =

# Matching Rule 9 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26269 = 0

# Matching Incoming Caller ID. Matching Rule 10
# String
P26296 =

# Matching Rule 10 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26297 = 0

# Ring Timeout (in seconds). Default is 60
# Number: 30 - 3600
# Mandatory
P576 = 60

# Send Anonymous (caller ID will be blocked if set to Yes). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P521 = 0

# Anonymous Call Rejection. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P546 = 0

# Auto Answer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P525 = 0

# Allow Auto Answer by Call-Info. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P538 = 0

# Custom Call-Info for Auto Answer.
# String
P2556 =

# Refer-To Use Target Contact. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P569 = 0

# Transfer on conference Hang-Up. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2504 = 0

#Disable Recovery on Blind Transfer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2584 = 0

# No Key Entry Timeout. Default is 4
# Number: 1 - 15
# Mandatory
P591 = 4

# Use # as Dial Key. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P592 = 1

# On Hold Reminder Tone. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P26206 = 0

##########################################
# Account 3/Feature Codes
##########################################
#Enable Call Features.  0 - No, 1 - Yes. Default is 1
#Number: 0, 1
#Mandatory
P520 = 1

#Do Not Disturb (DND) - On
# String
P2544 =

#Do Not Disturb (DND) - Off
# String
P2545 =

#Delayed Call Forward Wait Time (in seconds). Default is 20
#Number: 1 - 120
#Mandatory
P570 = 20
####################################################################
# Account 4  - GXP1782/1780 Only
####################################################################
##########################################
# Account 4/General Settings
##########################################
# Account Active. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
# P601 = 1

# Account Name
# String
# P617 =

# SIP Server
# String
# P602 =

# Secondary SIP Server
# String
# P2612 =

# Outbound Proxy
# String
# P603 =

# Backup Outbound Proxy
# String
# P2633 =

# BLF Server
# String
# P2675 =

# SIP User ID
# String
# P604 =

# Authenticate ID
# String
# P605 =

# Authenticate password
# String
# P606 =

# Name
# String
# P607 =

# Voice Mail UserID
# String
# P626 =

# Account Display
# Number: 0 - User Name, 1 - User Identification. Default is 0.
# Mandatory
P2680 = 0

##########################################
# Account 4/Network Settings
##########################################
# DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
# P608 = 0

# Primary IP. Maximum 15 characters
# String
# P2608 =

# Backup IP 1
# String
# P2609 =

# Backup IP 2
# String
# P2610 =

# NAT Traversal. 0 - No, 1 - STUN, 2 - keep alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0
# Number: 0, 1, 2, 3, 4, 5
# Mandatory
# P614 = 0

# Support Rport (RFC 3581). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P647 = 1

# Proxy-Require (A SIP extension to enable firewall penetration). Max length is 64 characters
# String
# P618 =

# Use SBC. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P26390 = 0

##########################################
# Account 4/SIP Settings/Basic Settings
##########################################
# TEL URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0
# Number: 0, 1, 2
# Mandatory
# P609 = 0

# SIP Registration. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
# P610 = 1

# Unregister On Reboot. 0 - No, 1 - Yes, 2 - Instance. Default is 0
# Number: 0, 1
# Mandatory
# P611 = 0

# Register Expiration (in minutes). Default is 60. Max is 45 days
# Number: 1 - 64800
# Mandatory
# P612 = 60

# Subscribe Expiration. Default is 60.
# Number: 1-64800
# Mandatory
# P26351 = 60

# Reregister before Expiration (in seconds). Default is 0
# Number: 0 - 64800
# Mandatory
# P2630 = 0

# Enable OPTIONS Keep Alive. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2697 = 0

# OPTIONS Keep Alive Interval. Default is 30.
# Number
# Mandatory
# P2698 = 30

# OPTIONS Keep Alive Max Lost. Default is 3.
# Number
# Mandatory
# P2699 = 3

# Local SIP port. Default is 5066
# Number
# Mandatory
# P613 = 5066

# SIP Registration Failure Retry Wait Time (in seconds). Default is 20
# Number: 1 - 3600
# Mandatory
# P671 = 20

# SIP T1 Timeout. RFC 3261 T1 value (RTT estimate)
# 50 - 0.5 sec, 100 - 1 sec, 200 - 2 sec. Default is 100
# Number: 50, 100, 200
# Mandatory
# P640 = 50

# SIP T2 Timeout. RFC 3261 T2 value. The maximum retransmit interval for non-INVITE requests and INVITE responses
# 200 - 2 sec, 400 - 4 sec, 800 - 8 sec. Default is 400
# Number: 200, 400, 800
# Mandatory
# P641 = 400

# SIP Transport. 0 - UDP, 1 - TCP. 2 - TCP/TLS. Default is 0
# Number: 0, 1, 2
# Mandatory
# P648 = 0

# SIP URI Scheme When Using TLS. 0 - sip, 1 - sips. Default is 1
# Number: 0, 1
# Mandatory
# P2629 = 1

# Use Actual Ephemeral Port in Contact with TCP/TLS. 0 - No, 1- Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2631 = 0

# Outbound Proxy Mode. 0 - in route, 1 - not in route, 2 - always send to. Default is 0
# Number: 0, 1, 2
# Mandatory
# P2605 = 0

# Support SIP Instance ID. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
# P689 = 1

# SUBSCRIBE for MWI (Whether or not send SUBSCRIBE for Message Waiting Indication). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P615 = 0

# SUBSCRIBE for Registration. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2619 = 0

# Enable 100rel. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P635 = 0

# Callee ID Display. 0 - Auto, 1 - Disabled, 2 - To Header. Default is 0
# Number: 0, 1, 2
# Mandatory
# P26325= 0

# Caller ID Display. 0 - Auto, 1 - Disabled, 2 - From Header. Default is 0
# Number: 0, 1, 2
# Mandatory
# P2624 = 0

# Ignore Alert-Info header
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P26318 = 0

# Add Auth Header On Initial REGISTER. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2659 = 0

###############################################################
# Account 4/SIP Settings/Custom SIP Headers
###############################################################
# Use Privacy Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
# P2638 = 0

# Use P-Preferred-Identity Header
# 0 - Default, 1 - No, 2 - Yes. Default is 0
# Number: 0, 1, 2
# Mandatory
# P2639 = 0

# Use X-Grandstream-PBX Header. Default is 1.
# Number 0, 1
# Mandatory
# P26354 = 1

# Use P-Access-Network-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
# P26358 = 1

# Use P-Emergency-Info Header. 0 - No, 1 - Yes. Default is 1.
# Number 0, 1
# Mandatory
# P26359 = 1

# Use MAC Header
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P26361 = 0

##########################################
# Account 4/SIP Settings/Advanced Features
##########################################
# Line-Seize Timeout (in seconds). Default is 15
# Number: 15 - 60
# Mandatory
# P2613 = 15

# Eventlist BLF URI
# String
# P644 =

# Auto Provision Eventlist BLFs. 0-Disabled, 1-Enabled. Default is 0
# Number:0,1
# Mandatory
# P2689 = 0

# Conference URI
# String
# P2618 =

# Music On Hold URI
# String
# P2650 =

# Force BLF Call-pickup by prefix. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P6755 = 0

# BLF Call-pickup Prefix. Default is **
# String
# Mandatory
# P681 = **

# Call Pickup Barge-In Code
# String
# P26346 =

# PUBLISH for Presence. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P688 = 0

# Omit charset=UTF-8 in MESSAGE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2655 = 0

# Special Feature. 100 - Standard, 101 - Nortel MCS, 102- Broadsoft, 108 - CBCOM, 109 - RNK, 110 - Sylantro, 117 - Huawei IMS. Default is 100
# Number: 100, 101, 102, 108, 109, 110, 117
# Mandatory
# P624 = 100

# Broadsoft
# Broadsoft Call Center. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2641 = 0

# Hoteling Event. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2642 = 0

# Call Center Status. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2643 = 0

# Feature Key Synchronization. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
# P2625 = 0

# Broadsoft Call Park. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
# P2688 = 0
##########################################
# Account 4/SIP Settings/Session Timer
##########################################
# Enable Session Timer. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
# P2695 = 1

# Session Expiration (in seconds). Default is 180
# Number: 90 - 64800
# Mandatory
# P634 = 180

# Minimum SE (in seconds). Default is 90 seconds. This value must be lower than or equal to P634
# Number: 90 - 64800
# Mandatory
# P627 = 90

# Caller Request Timer (Request for timer when calling). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P628 = 0

# Callee Request Timer (Request for timer when called. i.e. if remote party supports timer but did not request for one)
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P629 = 0

# Force Timer (Still use timer when remote party does not support timer). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P630 = 0

# UAC Specify Refresher. 0 - omit, 1 - UAC, 2 - UAS. Default is 0
# Number: 0 - 2
# Mandatory
# P632 = 0

# UAS Specify Refresher. 1 - UAC, 2 - UAS. Default is 1
# Number: 1, 2
# Mandatory
# P633 = 1

# Force INVITE (Always refresh with INVITE instead of UPDATE even when remote party supports UPDATE).
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P631 = 0
##########################################
# Account 4/SIP Settings/Security Settings
##########################################
# Check Domain Certificates. When set to Yes/Enabled, the domain certificate will be checked as defined in RFC5922
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2611 = 0

# Validate Certification Chain. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2667 = 0

# Validate Incoming Messages. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2606 = 0

# Check SIP User ID for incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P649 = 0

# Accept Incoming SIP from Proxy Only. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2647 = 0

# Authenticate Incoming INVITE. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2646 = 0
##########################################
# Account 4/Audio Settings
##########################################
# Preferred Vocoder
# 0 - PCMU, 2 - G.726-32, 8 - PCMA, 9 - G.722, 18 - G.729A/B, 4 - G.723.1, 98 - iLBC, 125 - OPUS
# choice 1. Default is 0
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P651 = 0

# choice 2. Default is 8
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P652 = 8

# choice 3. Default is 4
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P653 = 4

# choice 4. Default is 18
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P654 = 18

# choice 5. Default is 9
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P655 = 9

# choice 6. Default is 98
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P656 = 98

# choice 7. Default is 2
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P657 = 2

# choice 8. Default is 125
# Number: 0, 2, 4, 8, 9, 18, 98, 125
# Mandatory
# P658 = 125

# Use First Matching Vocoder in 200OK SDP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2648 = 0



# Disable Multiple m line in SDP
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P687 = 0

# SRTP Mode
# 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 3 - Optional. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
# P643 = 0

# Crypto Life Time
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2663 = 0

# Symmetric RTP. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P660 = 0

# Silence Suppression 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P685 = 0

# Jitter Buffer Type. 0 - Fixed, 1 - Adaptive. Default is 1
# Number: 0, 1
# Mandatory
# P698 = 1

# Jitter Buffer Length.
# 0 - 100ms, 1 - 200ms, 2 - 300ms, 3 - 400ms, 4 - 500ms, 5 - 600ms, 6 - 700ms, 7 - 800ms. Default is 2
# Number: 0, 1, 2, 3, 4, 5, 6, 7
# Mandatory
# P697 = 2

# Voice Frames per TX (up to 10/20/32/64 frames for G711/G726/G723/other codecs respectively). Default is 2
# Number: 1 - 64
# Mandatory
# P686 = 2

# G723 Rate. 0 - 6.3kbps encoding rate, 1 - 5.3kbps encoding rate. Default is 1
# Number: 0, 1
# Mandatory
# P693 = 1

# G.726-32 Packing Mode. 0 - ITU, 1 - IETF. Default is 0
# Number: 0, 1
# Mandatory
# P2623 = 0

# iLBC Frame Size. 0 - 20ms, 1 - 30ms. Default is 1
# Number: 0, 1
# Mandatory
# P695 = 1

# iLBC Payload Type. Default is 97
# Number: 96 - 127
# Mandatory
# P694 = 97

# OPUS Payload Type. Default is 123
# Number: 96 - 127
# Mandatory
# P2685 = 123

# DTMF Payload Type. Default is 101
# Number: 96 - 127
# Mandatory
# P696 = 101

# Send DTMF: In-audio. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2601 = 0

# Send DTMF: Via RTP (RFC2833). 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
# P2602 = 1

# Send DTMF: Via SIP INFO. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2603 = 0

##########################################
# Account 4/Call Settings
##########################################
# Early Dial (use "Yes" only if proxy supports 484 response). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P622 = 0

# Dial Plan Prefix
# String
# P619 =

# Dial Plan. Default is { x+ | \+x+ | *x+ | *xx*x+ }
# String
# Mandatory
# P659= { x+ | \+x+ | *x+ | *xx*x+ }

# Bypass Dial Plan
# String: contact,incoming,outgoing,dialing,mpk,api. Default is mpk
P2682 = mpk

# Call Log. 0 - Log All Calls, 1 - Log Incoming/Outgoing only (Missed calls NOT recorded), 2 - Disable Call Log. Default is 0
# Number: 0, 1, 2
# Mandatory
# P642 = 0

# Account Ring Tone.
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
# P623 = 0

# Matching Incoming Caller ID. Matching Rule 1
# String
# P1506 =

# Matching Rule 1 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
# P1507 = 0

# Matching Incoming Caller ID. Matching Rule 2
# String
# P1508 =

# Matching Rule 2 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
# P1509 = 0

# Matching Incoming Caller ID. Matching Rule 3
# String
# P1510 =

# Matching Rule 3 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
# P1511 = 0

# Matching Incoming Caller ID. Matching Rule 4
# String
P6734 =

# Matching Rule 4 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6735 = 0

# Matching Incoming Caller ID. Matching Rule 5
# String
P6736 =

# Matching Rule 5 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6737 = 0

# Matching Incoming Caller ID. Matching Rule 6
# String
P6738 =

# Matching Rule 6 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P6739 = 0

# Matching Incoming Caller ID. Matching Rule 7
# String
P26364 =

# Matching Rule 7 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26365 = 0

# Matching Incoming Caller ID. Matching Rule 8
# String
P26366 =

# Matching Rule 8 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26367 = 0

# Matching Incoming Caller ID. Matching Rule 9
# String
P26368 =

# Matching Rule 9 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26369 = 0

# Matching Incoming Caller ID. Matching Rule 10
# String
P26396 =

# Matching Rule 10 Distinctive Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P26397 = 0

# Ring Timeout (in seconds). Default is 60
# Number: 30 - 3600
# Mandatory
# P676 = 60

# Send Anonymous (caller ID will be blocked if set to Yes). 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P621 = 0

# Anonymous Call Rejection. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P646 = 0

# Auto Answer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P625 = 0

# Allow Auto Answer by Call-Info. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P638 = 0

# Custom Call-Info for Auto Answer.
# String
# P2656 =

# Refer-To Use Target Contact. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P669 = 0

# Transfer on conference Hang-Up. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2604 = 0

#Disable Recovery on Blind Transfer. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
# P2684 = 0

# No Key Entry Timeout. Default is 4
# Number: 1 - 15
# Mandatory
# P691 = 4

# Use # as Dial Key. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1, 2
# Mandatory
# P692 = 1

# On Hold Reminder Tone. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P26306 = 0

##########################################
# Account 4/Feature Codes
##########################################
#Enable Call Features.  0 - No, 1 - Yes. Default is 1
#Number: 0, 1
#Mandatory
# P620 = 1

#Do Not Disturb (DND) - On
# String
# P2644 =

# Do Not Disturb (DND) - Off
# String
# P2645 =

# Delayed Call Forward Wait Time (in seconds). Default is 20
# Number: 1 - 120
# Mandatory
# P670 = 20

##############################################################################
##  Settings/General Settings
##############################################################################
# Local RTP port. Default is 5004
# Number: 1024 - 65400. Must be even number
# Mandatory
P39 = 5004

# Use Random Port. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P78 = 0

# Keep-Alive Interval (in seconds). Default is 20
# Number: 10 - 160
# Mandatory
P84 = 20

# Use NAT IP. This will enable our SIP client to use this IP in the SIP/SDP message. Example 64.3.153.50
# String: a-z, A-Z, 0-9, ".", ":"
P101 =

# STUN server
# String
P76 =

# Public Mode. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1345 = 0

# Enable Fix For RTP Timestamp Jump. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P8389 = 0

# Test Password Strength
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P8468 = 0

##############################################################################
##  Settings/Call Features
##############################################################################
# Onhook Dial Barging. 0 - Disabled, 1 - Enabled. Default is 1
# Number: 0, 1
# Mandatory
#P8397 = 1

# Off-hook Auto Dial
# String
P71 =

# Off-hook Timeout (in seconds). Default is 30
# Number: 10 - 60
# Mandatory
P1485 = 30

# Enable Automatic Redial. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P8353 = 0

# Automatic Redial Times. Default is 10.
# Number: 1 - 100
# Mandatory
P8354 = 10

# Automatic Redial Interval. Default is 20.
# Number: 1 - 360
# Mandatory
P8355 = 20

# Bypass Dial Plan Through Call History and Directories. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P6758 = 0

# Disable Call Waiting. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P91 = 0

# Disable Call Waiting Tone. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P186 = 0

# Ring For Call Waiting
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P22102 = 0

# Disable Busy Tone on Remote Disconnect. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P6762 = 0

# Disable Direct IP Call. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1310 = 0

# Use Quick IP call mode. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P184 = 0

# Disable Conference. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1311 = 0

# Disable in-call DTMF display. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P338 = 0

# Mute Key Functions While Idle. 0 - DND, 1 - Idle Mute, 2 - Disabled. Default is 0
# Number: 0,1,2
# Mandatory
P1565 = 0

# Enable Auto Unmute. 0 - No, 1 - Yes. Default is 1
# Number: 0,1,2
# Mandatory
P8488 = 1

# Disable Transfer. 0 - No, 1 - Yes. Defauls is 0
# Number: 0, 1
# Mandatory
P1341 = 0

# In-call Dial Number on pressing transfer key
# String
P1525 =

# Attended Transfer Mode. 0 - Static, 1 - Dynamic. Default is 0
# Number: 0, 1
# Mandatory
P1376 = 0

# Do not Escape '#' as 23% in SIP URI. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1406 = 0

# Use Pound (#) For Redial. . 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P8504 = 1

# Click-To-Dial Feature. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P1561 = 0

# Write Timeout. Default is 300.
# Number
# Mandatory
P1433 = 300

# Max Unsaved Log. Default is 200.
# Number
# Mandatory
P1434 = 200

# Default call log type. 0 - Default, 1 - Broadsoft Call Log, 2 - Local Call Log.
# Number: 0,1,2. Default is 0.
# Mandatory
P6765 = 0

# Return Code When Refusing Incoming Call. Default is 0
# Number: 0,1,2,3. 0 - Busy, 1 - Temporarily Unavailable, 2 - Not Found(404), 3 - Decline(603).
# Mandatory
P8360 = 0

# Return Code When Enable DND. Default is 0
# Number: 0,1,2,3. 0 - Busy, 1 - Temporarily Unavailable, 2 - Not Found(404), 3 - Decline(603).
# Mandatory
P8361 = 0

# Local Call Recording Feature. 0 - Disable, 1 - Enable. Default is 0
# Number: 0, 1
# Mandatory
P6760 = 0

# Saved Local Call Recording Location. 0 - Internal Storage 1 - USB. Default is 0.
# Number: 0, 1
# Mandatory
P6761 = 0

# User-Agent Prefix
# String
P8358 =

# Predictive Dialing Feature. 0 - disabled, 1 - enabled. Default is 1
# Number: 0, 1
# Mandatory
P22126 = 1

# Enable Enhanced Acoustic Echo Canceller. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P22175 = 1

# Enable Diversion Information Display. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P22237 = 1

##############################################################################
##  Settings/Multicast Paging
##############################################################################
# Paging Barge. 0 - Disable, 1 - priority 1, 2 - priority 2, 3 - priority 3,
# 4 - priority 4, 5 - priority 5, 6 - priority 6, 7 - priority 7, 8 - priority 8,
# 9 - priority 9, 10 - priority 10. Default is 0.
# Number: 0 - 10
# Mandatory
P1566 = 0

# Paging Priority Active. 0 - Disabled, 1 - Enabled
# Number: 0, 1
# Mandatory
P1567 = 0

# Multicast Paging Codec. 18 - G.729A/B, 0 - PCMU, 8 - PCMA, 2 - G.726-32, 9 - G.722, 98 - iLBC
# Number: 18, 0, 8, 2, 9, 98. Default is 0.
# Mandatory
P1568 = 0

### Multicast Listening ###
# Priority 1
# Listening Address
# String
P1569 =

# Label
# String
P1570 =

# Priority 2
# Listening Address
# String
P1571 =

# Label
# String
P1572 =

# Priority 3
# Listening Address
# String
P1573 =

# Label
# String
P1574 =

# Priority 4
# Listening Address
# String
P1575 =

# Label
# String
P1576 =

# Priority 5
# Listening Address
# String
P1577 =

# Label
# String
P1578 =

# Priority 6
# Listening Address
# String
P1579 =

# Label
# String
P1580 =

# Priority 7
# Listening Address
# String
P1581 =

# Label
# String
P1582 =

# Priority 8
# Listening Address
# String
P1583 =

# Label
# String
P1584 =

# Priority 9
# Listening Address
# String
P1585 =

# Label
# String
P1586 =

# Priority 10
# Listening Address
# String
P1587 =

# Label
# String
P1588 =

##############################################################################
##  Settings/Ring Tone
##############################################################################
### Call Progress Tones ###
# Syntax: f1=val,f2=val[,c=on1/off1[-on2/off2[-on3/off3]]];
# (Frequencies are in Hz and cadence on and off are in 10ms)

# System Ring Tone
# String
# Mandatory
P345 = f1=440,f2=480,c=200/400;

# Dial Tone
# String
# Mandatory
P343 = f1=350,f2=440;

# Second Dial Tone
# String
# Mandatory
P2909 = f1=350,f2=440;

# Message Waiting Tone
# String
# Mandatory
P344 = f1=350,f2=440,c=10/10;

# Ring Back Tone
# String
# Mandatory
P346 = f1=440,f2=480,c=200/400;

# Call-Waiting Tone
# String
# Mandatory
P347 = f1=440,f2=440,c=25/525;

# Call-Waiting Tone Gain. 0 - Low, 1 - Medium, 2 - High. Default is 0
# Number: 0, 1, 2
# Mandatory
P1555 = 0

# Busy Tone
# String
# Mandatory
P348 = f1=480,f2=620,c=50/50;

# Reorder Tone
# String
# Mandatory
P349 = f1=480,f2=620,c=25/25;

# Speaker Ring Volume
# Number: 0-7. Default is 7.
# Mandatory
P8352 = 7

# Notification Tone Volume. Default is 7.
# Number: 0 - 7
# Mandatory
P8399 = 7

# Default Ringtone
# 0 - system ring tone, 1 - custom ringtone 1, 2 - custom ringtone 2, 3 - custom ringtone, 4 - Silent, 5 - Default Ringtone,
# 6 - Custom Ringtone 4, 7 - Custom Ringtone 5, 8 - Custom Ringtone 6, 9 - Custom Ringtone 7, 10 - Custom Ringtone 8,
# 11 - Custom Ringtone 9, 12 - Custom Ringtone 10. Default is 0
# Number: 0 - 12
# Mandatory
P8398 = 0

##############################################################################
##  Settings/Audio Control
##############################################################################
# HEADSET Key Mode. 0 - Default Mode, 1 - Toggle Headset/Speaker. Default is 0
# Number: 0, 1
# Mandatory
P1312 = 0

# Headset Type. 0 - Normal, 1 - Plantronics EHS. Default is 0
# Number: 0, 1
# Mandatory
P1487 = 0

# Always Ring Speaker. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1439 = 0

# Headset TX gain(db). 1 - -6, 0 - 0, 2 - +6, 3 - -9, 4 - +9, 5 - -12, 6 - -15. Default is 0
# Number: 0, 1, 2, 3, 4, 5, 6
# Mandatory
P1301 = 0

# Headset RX gain(db). 1 - -6, 0 - 0, 2 - +6, 3 - -9, 4 - +9. Default is 0
# Number: 0, 1, 2, 3, 4
# Mandatory
P1302 = 0

# Handset TX gain(db). 1 - -6, 0 - 0, 2 - +6. Default is 0
# Number: 0, 1, 2
# Mandatory
P1464 = 0

##############################################################################
##  Settings/LCD Display
##############################################################################
# Backlight Brightness.
# Active. Default is 6.
# Number
# Mandatory
P334 = 6

# Backlight Brightness.
# Idle. Default is 2.
# Number
# Mandatory
P335 = 2

# Active Backlight Timeout
# Number: 1-90, 0 - Disabled. Default is 1.
# Mandatory
P8356 = 1

# Disable Missed Call Backlight. 0 - No, 1 - Yes, 2 - Yes, but flash MWI LED. Default is 0
# Number: 0, 1, 2
# Mandatory
P351 = 0

# Softkeys
# Hide System Softkey on Main Page. Next, History, ForwardAll, Redial.
# String
P8348 =

###############################################################
# Settings/LED Control
###############################################################
# Disable VM/MSG power light flash
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P8371 = 0

##############################################################################
##  Settings/Date and Time
##############################################################################
# NTP Server
# String
P30 = us.pool.ntp.org

# NTP Update Interval
# String
# Number: 5 - 1440o< Default is 1440
P5005 = 1440

# Allow DHCP Option 42 to override NTP server. 0 - No, 1 - Yes. Default is 1
# When set to Yes(1), it will override the configured NTP server
# Number: 0, 1
# Mandatory
P144 = 1

# Time Zone
# P value                                                                                         Web GUI Option

# auto                                                                                               Automatic
# TZA+12                                                                                        GMT-12:00 (International Date Line West)
# TZB+11                                                                                        GMT-11:00 (Midway Island, Samoa)
# HAW10                                                                                         GMT-10:00 (US Hawaiian Time)
# AKST9AKDT                                                                                  GMT-9:00 (US Alaska Time)
# PST8PDT                                                                                     GMT-8:00 (US Pacific Time, Los Angeles)
# PST8PDT,M3.2.0,M11.1.0                                                                     GMT-08:00 (Baja California)
# MST7MDT                                                                                     GMT-07:00 (US Mountain Time, Denver)
# MST7                                                                                         GMT-07:00 (Mountain Time (Arizona, no DST))
# MST7MDT,M4.1.0,M10.5.0                                                                      GMT-07:00 (Chihuahua, La Paz, Mazatlan)
# CST6CDT                                                                                       GMT-06:00 (Central Time)
# CST+6                                                                                            GMT-06:00 (Central America)
# CST6CDT,M4.1.0,M10.5.0                                                                      GMT-06:00 (Guadalajara, Mexico City, Monterrey)
# EST5EDT                                                                                       GMT-05:00 (Eastern Time)
# EST5                                                                                               GMT-05:00 (Eastern Time without daylight saving)
# TZf+4:30                                                                                         GMT-04:30 (Caracas)
# AST4ADT                                                                                       GMT-04:00 (Atlantic Time)
# AST4ADT,M3.2.0,M11.1.0                                                                    GMT-04:00 (Atlantic Time (New Brunswick))
# NST+3:30NDT+2:30,M3.2.0/00:01:00,M11.1.0/00:01:00                                        GMT-03:30 (Newfoundland Time)
# TZK+3                                                                                              GMT-03:00 (Greenland)
# BRST+3BRDT+2,M10.3.0,M2.3.0                                                                   GMT-03:00 (Brazil, Sao Paulo)
# UTC+3                                                                                              GMT-02:00 (Argentina)
# TZL+2                                                                                               GMT-02:00 (Mid-Atlantic)
# TZM+1                                                                                              GMT-01:00 (Azores, Cape Verdi Is.)
# TZN+0                                                                                              GMT (Edinburgh, Casablanca, Monrovia)
# GMT+0BST-1,M3.5.0/01:00:00,M10.5.0/02:00:00                                             GMT (London, Great Britain)
# WET-0WEST-1,M3.5.0/01:00:00,M10.5.0/02:00:00                                            GMT (Lisbon, Portugal)
# GMT+0IST-1,M3.5.0/01:00:00,M10.5.0/02:00:00                                           GMT (Dublin, Ireland)
# CET-1CEST-2,M3.5.0/02:00:00,M10.5.0/03:00:00                       GMT+01:00 (Paris, Vienna, Warsaw, Roma, Madrid, Prague, Berlin, Budapest, Amsterdam, Belgium)
# TZP-2                                                                     GMT+02:00 (Israel, Cairo, Athens, Istanbul, Buchrest)
# EET-2EEST-3,M3.5.0/03:00:00,M10.5.0/04:00:00                         GMT+02:00 (Helsinki, Athens, Tallinn)
# EET-2EEST,M3.5.0/3,M10.5.0/4                                                   GMT+02:00 (Kyiv, Ukraine)
# TZQ-3                                                                                               GMT+03:00 (Kuwait, Baghdad, Tehran, Nairobi)
# MSK-3                                                                                           GMT+03:00 (Moscow, Russia)
# TZR-4                                                                                               GMT+04:00 (Abu Dhabi, Baku)
# TZS-5                                                                                               GMT+05:00 (Islamabad, Ekaterinburg, Karachi, Tashkent)
# TZT-5:30                                                                                          GMT+05:30 (Chennai, New Delhi, Mumbai)
# TZU-5:45                                                                                          GMT+05:45 (Kathmandu)
# TZV-6                                                                                               GMT+06:00 (Almaty, Astana, Dhaka, Novosibirsk)
# TZW-6:30                                                                                        GMT+06:30 (Rangoon)
# TZX-7                                                                                               GMT+07:00 (Bankok, Hanoi, Krasnoyarsk)
# WIB-7                                                                                               GMT+07:00 (Jakarta)
# TZY-8                                                                                               GMT+08:00 (Beijing, Taipei, Kuala Lumpur, Irkutsk)
# SGT-8                                                                                              GMT+08:00 (Singapore)
# ULAT-8                                                                                            GMT+08:00 (Ulaanbaatar, Mongolia)
# WST-8                                                                                             GMT+08:00 (Perth)
# TZZ-9                                                                                               GMT+09:00 (Japan, Korea, Yakutsk)
# CST-9:30CDT-10:30,M10.5.0/02:00:00,M3.5.0/03:00:00                                            GMT+09:30 (Adelaide)
# CST-9:30                                                                                          GMT+09:30 (Darwin)
# TZb-10                                                                                              GMT+10:00 (Guam)
# EST-10EDT-11,M10.1.0/02:00:00,M3.5.0/03:00:00                                                   GMT+10:00 (Hobart)
# EST-10EDT-11,M10.5.0/02:00:00,M3.5.0/03:00:00                                           GMT+10:00 (Sydney, Melbourne, Canberra)
# EST-10                                                                                              GMT+10:00 (Brisbane)
# TZc-11                                                                                               GMT+11:00 (Magadan, Solomon Is., New Caledonia)
# NZST-12NZDT-13,M9.5.0/02:00:00,M4.1.0/03:00:00                                               GMT+12:00 (Auckland, Wellington)
# TZd-12                                                                                               GMT+12:00 (Fiji)
# TZe-13                                                                                               GMT+13:00 (Nuku'alofa)
# customize                                                                                          Self-Defined Time Zone

# String
# Mandatory
P64 = auto

# Allow DHCP Option 2 to override Time Zone setting. 0 - No, 1 - Yes. Default is 1
# When set to Yes(1), it will override the configured Time Zone setting if available
# Number: 0, 1
# Mandatory
P143 = 1

# Self Defined Time zone. Max length allowed is 64 characters
# String
# Mandatory
P246 = MTZ+6MDT+5,M4.1.0,M11.1.0

# Date Display Format
# 0: yyyy-mm-dd      eg. 2011-10-31
# 1: mm-dd-yyyy      eg. 10-31-2011
# 2: dd-mm-yyyy      eg. 31-10-2011
# 3: dddd, MMMM dd   eg. Monday, October 31
# 4: MMMM dd, dddd   eg. October 31, Monday
# Number: 0, 1, 2, 3, 4
# Mandatory
P102 = 0

# Time Display Format. 0 - 12 Hour, 1 - 24 Hour
# Number: 0, 1
# Mandatory
P122 = 0

##############################################################################
##  Settings/Web Service
##############################################################################
# Use Auto Location Service. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P8338 = 1

##############################################################################
##  Settings/XML Applications
##############################################################################
# Idle Screen XML Download. 0 - Disable, 1 - Enabled, use HTTP, 2 - Enabled, use TFTP, 3 - Enabled, use HTTPS. Default is 0.
# Number: 0,1,2,3
# Mandatory
P340 = 0

# Download Screen XML at Boot-up. 0 - No, 1 - Yes. Default is 0.
# Number: 0,1
# Mandatory
P1349 = 0

# Use Custom Filename. 0 - No, 1 - Yes. Default is 0.
# Number: 0,1
# Mandatory
P1343 = 0

# Idle Screen XML Server Path.
# String
P341 =

##############################################################################
##  Settings/Programmable Keys
##############################################################################
# Settings/Programmable Keys/Virtual Multi-Purpose Keys Settings
##############################################################################
# Use Long Label. 0 - No, 1 - Yes, 2 - Maximum Length. Default is 0.
# Number: 0, 1, 2
# Mandatory
P8346 = 0

# Key Mode. 0 - Line Mode, 1 - Account Mode. Default is 0.
# Number: 0, 1
# Mandatory
P8369 = 0

# Show Keys Label. 1 - Show, 2 - Hide. Default is 1.
# Number: 1, 2
# Mandatory
P8386 = 1

# Transfer Mode via VPK
# Number: 0 - Blind Transfer, 1 - Attended Transfer, 2 - New Call. Default is 0.
# Mandatory
P8390 = 0

# Enable transfer via non-Transfer MPK
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P22162 = 0

######################################################################################
# Virtual Multi-Purpose Keys (VPKs)
# Note: 1. Fixed VPKs can only be edited. Adding or deleting Fixed VPK is not allowed.
#       2. Even if a Dynamic VPK has mode set to None, it should be added in sequence.
#          Skipping one will remove everything after that VPK.
######################################################################################
######################################################################################
############################ For GXP1782/1780 ########################################
######################################################################################
######################################################################################
# VPK 1-- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1363 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P1364 = 0

# Description. Max length allowed is 32 characters.
# String
# P1465 =

# Value. Max length allowed is 64 characters.
# String
# P1466 =

######################################################################################
# VPK 2-- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1365 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 1.
# Mandatory
# P1366 = 1

# Description. Max length allowed is 32 characters.
# String
# P1467 =

# Value. Max length allowed is 64 characters.
# String
# P1468 =

######################################################################################
# VPK 3 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1367 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 2.
# Mandatory
# P1368 = 2

# Description. Max length allowed is 32 characters.
# String
# P1469 =

# Value. Max length allowed is 64 characters.
# String
# P1470 =

######################################################################################
# VPK 4 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1369 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 3.
# Mandatory
# P1370 = 3

# Description. Max length allowed is 32 characters.
# String
# P1471 =

# Value. Max length allowed is 64 characters.
# String
# P1472 =

######################################################################################
# VPK 5 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1371 = 29

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 4.
# Mandatory
# P1372 = 0

# Description. Max length allowed is 32 characters.
# String
# P1473 =

# Value. Max length allowed is 64 characters.
# String
# P1474 =

######################################################################################
# VPK 6 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1373 = 25

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 5.
# Mandatory
# P1374 = 0

# Description. Max length allowed is 32 characters.
# String
# P1475 =

# Value. Max length allowed is 64 characters.
# String
# P1476 =

######################################################################################
# VPK 7--fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is -1.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P23800 = 30

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23801 = 0

# Description.
# String.
# P23802 =

# Value.
# String.
# P23803 =

#####################################################################################
# VPK 8--fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is -1.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P23804 = 27

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23805 = 0

# Description.
# String.
# P23806 =

# Value.
# String.
# P23807 =

######################################################################################
# VPK 9--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23808 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23809 = 0

# Description.
# String.
# P23810 =

# Value.
# String.
# P23811 =

######################################################################################
# VPK 10--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23812 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23813 = 0

# Description.
# String.
# P23814 =

# Value.
# String.
# P23815 =

######################################################################################
# VPK 11--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23816 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23817 = 0

# Description.
# String.
# P23818 =

# Value.
# String.
# P23819 =

######################################################################################
# VPK 12--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23820 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23821 = 0

# Description.
# String.
# P23822 =

# Value.
# String.
# P23823 =

######################################################################################
# VPK 13--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23824 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23825 = 0

# Description.
# String.
# P23826 =

# Value.
# String.
# P23827 =

######################################################################################
# VPK 14--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23828 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23829 = 0

# Description.
# String.
# P23830 =

# Value.
# String.
# P23831 =

######################################################################################
# VPK 15--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23832 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23833 = 0

# Description.
# String.
# P23834 =

# Value.
# String.
# P23835 =

#####################################################################################
# VPK 16--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23836 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23837 = 0

# Description.
# String.
# P23838 =

# Value.
# String.
# P23839 =

#####################################################################################
# VPK 17--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23840 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23841 = 0

# Description.
# String.
# P23842 =

# Value.
# String.
# P23843 =

#####################################################################################
# VPK 18--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23844 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23845 = 0

# Description.
# String.
# P23846 =

# Value.
# String.
# P23847 =

#####################################################################################
# VPK 19--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23848 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23849 = 0

# Description.
# String.
# P23850 =

# Value.
# String.
# P23851 =

#####################################################################################
# VPK 20--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23852 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23853 = 0

# Description.
# String.
# P23854 =

# Value.
# String.
# P23855 =

#####################################################################################
# VPK 21--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23856 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23857 = 0

# Description.
# String.
# P23858 =

# Value.
# String.
# P23859 =

#####################################################################################
# VPK 22--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23860 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23861 = 0

# Description.
# String.
# P23862 =

# Value.
# String.
# P23863 =

#####################################################################################
# VPK 23--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23864 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23865 = 0

# Description.
# String.
# P23866 =

# Value.
# String.
# P23867 =

#####################################################################################
# VPK 24--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23868 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23869 = 0

# Description.
# String.
# P23870 =

# Value.
# String.
# P23871 =

#####################################################################################
# VPK 25--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23872 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23873 = 0

# Description.
# String.
# P23874 =

# Value.
# String.
# P23875 =

#####################################################################################
# VPK 26--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23876 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23877 = 0

# Description.
# String.
# P23878 =

# Value.
# String.
# P23879 =

#####################################################################################
# VPK 27--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23880 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23881 = 0

# Description.
# String.
# P23882 =

# Value.
# String.
# P23883 =

#####################################################################################
# VPK 28--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23884 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23885 = 0

# Description.
# String.
# P23886 =

# Value.
# String.
# P23887 =

#####################################################################################
# VPK 29--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23888 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23889 = 0

# Description.
# String.
# P23890 =

# Value.
# String.
# P23891 =

#####################################################################################
# VPK 30--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23892 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23893 = 0

# Description.
# String.
# P23894 =

# Value.
# String.
# P23895 =

#####################################################################################
# VPK 31--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23896 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23897 = 0

# Description.
# String.
# P23898 =

# Value.
# String.
# P23899 =

#####################################################################################
# VPK 32--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23900 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23901 = 0

# Description.
# String.
# P23902 =

# Value.
# String.
# P23903 =
######################################################################################
############################ For GXP1760 #############################################
######################################################################################
# VPK 1-- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1363 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P1364 = 0

# Description. Max length allowed is 32 characters.
# String
# P1465 =

# Value. Max length allowed is 64 characters.
# String
# P1466 =

######################################################################################
# VPK 2-- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1365 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 1.
# Mandatory
# P1366 = 1

# Description. Max length allowed is 32 characters.
# String
# P1467 =

# Value. Max length allowed is 64 characters.
# String
# P1468 =

######################################################################################
# VPK 3 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1367 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 2.
# Mandatory
# P1368 = 2

# Description. Max length allowed is 32 characters.
# String
# P1469 =

# Value. Max length allowed is 64 characters.
# String
# P1470 =

######################################################################################
# VPK 4 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1369 = 29

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 3.
# Mandatory
# P1370 = 0

# Description. Max length allowed is 32 characters.
# String
# P1471 =

# Value. Max length allowed is 64 characters.
# String
# P1472 =

######################################################################################
# VPK 5 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1371 = 25

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.  Default is 4.
# Mandatory
# P1372 = 0

# Description. Max length allowed is 32 characters.
# String
# P1473 =

# Value. Max length allowed is 64 characters.
# String
# P1474 =

######################################################################################
# VPK 6 -- fixed VPK
######################################################################################
# Key Mode for line VPK. Default  is 0.
# Number: -1, 0, 1, 10 -- 20, 21, 23-27, 29, 30, 38
# -1 - None, 0 - Line, 1 - shared line, 10 - Speed Dial, 11 - BLF, 12 - Presence Watcher
# 13 - Eventlist BLF, 14 - Speed Dial via active account, 15 - DialDTMF
# 16 - Voicemail, 17 - Call Return, 18 - Transfer, 19 - CallPark, 20 - Intercom
# 21 - LDAP Search, 23 - Multicast Paging, 24 - Record, 25 - Call Log
# 26 - Monitored Call Park, 27 - Menu ,29-Information, 30-Message, 38-Provision
# Mandatory
# P1373 = 27

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 5.
# Mandatory
# P1374 = 0

# Description. Max length allowed is 32 characters.
# String
# P1475 =

# Value. Max length allowed is 64 characters.
# String
# P1476 =

######################################################################################
# VPK 7--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23800 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23801 = 0

# Description.
# String.
# P23802 =

# Value.
# String.
# P23803 =

#####################################################################################
# VPK 8--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23804 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23805 = 0

# Description.
# String.
# P23806 =

# Value.
# String.
# P23807 =

######################################################################################
# VPK 9--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23808 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23809 = 0

# Description.
# String.
# P23810 =

# Value.
# String.
# P23811 =

######################################################################################
# VPK 10--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23812 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23813 = 0

# Description.
# String.
# P23814 =

# Value.
# String.
# P23815 =

######################################################################################
# VPK 11--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23816 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23817 = 0

# Description.
# String.
# P23818 =

# Value.
# String.
# P23819 =

######################################################################################
# VPK 12--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23820 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2. Default is 0.
# Mandatory
# P23821 = 0

# Description.
# String.
# P23822 =

# Value.
# String.
# P23823 =

######################################################################################
# VPK 13--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23824 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23825 = 0

# Description.
# String.
# P23826 =

# Value.
# String.
# P23827 =

######################################################################################
# VPK 14--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23828 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23829 = 0

# Description.
# String.
# P23830 =

# Value.
# String.
# P23831 =

######################################################################################
# VPK 15--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23832 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23833 = 0

# Description.
# String.
# P23834 =

# Value.
# String.
# P23835 =

#####################################################################################
# VPK 16--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23836 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23837 = 0

# Description.
# String.
# P23838 =

# Value.
# String.
# P23839 =

#####################################################################################
# VPK 17--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23840 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23841 = 0

# Description.
# String.
# P23842 =

# Value.
# String.
# P23843 =

#####################################################################################
# VPK 18--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23844 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23845 = 0

# Description.
# String.
# P23846 =

# Value.
# String.
# P23847 =

#####################################################################################
# VPK 19--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23848 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23849 = 0

# Description.
# String.
# P23850 =

# Value.
# String.
# P23851 =

#####################################################################################
# VPK 20--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23852 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23853 = 0

# Description.
# String.
# P23854 =

# Value.
# String.
# P23855 =

#####################################################################################
# VPK 21--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23856 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23857 = 0

# Description.
# String.
# P23858 =

# Value.
# String.
# P23859 =

#####################################################################################
# VPK 22--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23860 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23861 = 0

# Description.
# String.
# P23862 =

# Value.
# String.
# P23863 =

#####################################################################################
# VPK 23--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23864 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23865 = 0

# Description.
# String.
# P23866 =

# Value.
# String.
# P23867 =

#####################################################################################
# VPK 24--Dynamic VPK
######################################################################################
# Key Mode for Dynamic VPK.
# Number: -1 -- 11, 13 - 17, 19, 20, 28
# -1 - None, 0 - Speed Dial, 1 - BLF, 2 - Presence Watcher
# 3 - Eventlist BLF, 4 - Speed Dial via active account, 5 - DialDTMF
# 6 - Voice Mail, 7 - Call Return, 8 - Transfer, 9 - Call Park, 10 - Intercom
# 11 - LDAP Search, 13 - Multicast Paging, 14 - Record, 15 - Call Log
# 16 - Monitored Call Park, 17 - Menu, 19 - Information, 20 - Message, 28 - Provision
# Mandatory
# P23868 = -1

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3
# Number: 0, 1, 2.
# Mandatory
# P23869 = 0

# Description.
# String.
# P23870 =

# Value.
# String.
# P23871 =

######################################################################################
## Programmable Keys/Idle Screen SoftKeys
######################################################################################
# Softkey 1
######################################################################################
# Key Mode.
# 0 - Default, 10 - Speed Dial, 14 - Speed Dial via active account, 16 - Voicemail,
# 17 - CallReturn, 20 - Intercom, 21 - LDAP Search, 25 - Call Log, 27 - Menu,
# 29 - Information, 30 - Message, 38 - Provision
# Number: 0,10,14,16,17,20,21,25,27,29,30,38
# Mandatory
P2987 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3, 3 - Account 4 (GXP1782/1780 only)
# Number: 0, 1, 2, 3
# Mandatory
P2988 = 0

# Description.
# String.
# P2989 =

# Value.
# String.
# P2990 =

######################################################################################
# Softkey 2
######################################################################################
# Key Mode.
# 0 - Default, 10 - Speed Dial, 14 - Speed Dial via active account, 16 - Voicemail,
# 17 - CallReturn, 20 - Intercom, 21 - LDAP Search, 25 - Call Log, 27 - Menu,
# 29 - Information, 30 - Message, 38 - Provision
# Number: 0,10,14,16,17,20,21,25,27,29,30,38
# Mandatory
P2991 = 0

# Account. 0 - Account 1, 1 - Account 2, 2 - Account 3, 3 - Account 4 (GXP1782/1780 only)
# Number: 0, 1, 2, 3
# Mandatory
P2992 = 0

# Description.
# String.
# P2993 =

# Value.
# String.
# P2994 =
##############################################################################
##  Settings/Broadsoft XSI
##############################################################################
# Broadsoft XSI
# Server address
# String
P1591 =

# Port
# Number
P1592 =

# XSI Authentication Type. 0 - Login Credentials, 1 - SIP Credentials, 2 - Account 1, 3 - Account 2, 4 - Account 3, 5 - Account 4. Default is 0.
# Number: 0,1,2,3,4,5.
# Mandatory
P22054 = 0

# Login Credentials
# Login Username
# String
P1593 =

# Login Password
# String
P1594 =

# SIP Credentials
# SIP UserName
# String
P6772 =

# SIP User ID
# String
P22034 =

# SIP Password
# String
P6773 =

# Network Directories
### Group Directory ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2971 = 0

# Name.
# String
P2972 =

### Enterprise Directory ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2973 = 0

# Name.
# String
P2974 =

### Group Common ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2975 = 0

# Name.
# String
P2976 =

### Enterprise Common ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2977 = 0

# Name.
# String
P2978 =

### Personal Directory ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2979 = 0

# Name.
# String
P2980 =

### Missed Call Log ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2981 = 0

# Name.
# String
P2982 =

### Placed Call Log ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2983 = 0

# Name.
# String
P2984 =

### Received Call Log ###
# Disabled/Enabled. 0 - Disabled, 1 - Enabled.
# Number: 0, 1. Default is 0
# Mandatory
P2985 = 0

# Name.
# String
P2986 =

##############################################################################
##  Settings/External Service
##############################################################################
# Grandstream Door System.

# Service Type
# Number: -1 - None, 0 - GDS. Default is 0.
# Mandatory

P32000 = 0
P32005 = 0
P32010 = 0
P32015 = 0
P32020 = 0
P32025 = 0
P32030 = 0
P32035 = 0
P32040 = 0
P32045 = 0

# Account
# Number: 0 - Account 1, 1 - Account 2, 2 - Account 3, 3 - Account 4. Default is 0.
# Mandatory

P32001 = 0
P32006 = 0
P32011 = 0
P32016 = 0
P32021 = 0
P32026 = 0
P32031 = 0
P32036 = 0
P32041 = 0
P32046 = 0

# System Identification
# String

P32002 =
P32007 =
P32012 =
P32017 =
P32022 =
P32027 =
P32032 =
P32037 =
P32042 =
P32047 =

# System Number
# String

P32003 =
P32008 =
P32013 =
P32018 =
P32023 =
P32028 =
P32033 =
P32038 =
P32043 =
P32048 =

# Access Password
# String

P32004 =
P32009 =
P32014 =
P32019 =
P32024 =
P32029 =
P32034 =
P32039 =
P32044 =
P32049 =

##############################################################################
##  Settings/Outbound Notification
##############################################################################
######################################################################################
# Action URL
######################################################################################
# Setup Completed.
# String
P8304 =

# Registered.
# String
P8305 =

# Unregistered.
# String
P8306 =

# Off Hook.
# String
P8308 =

# On Hook.
# String
P8309 =

# Incoming Call.
# String
P8310 =

# Outgoing Call
# String
P8311 =

# Missed Call
# String
P8312 =

# Established Call
# String
P8313 =

# Terminated Call
# String
P8314 =

# Open DND
# String
P8316 =

# Close DND
# String
P8317 =

# Open Forward
# String
P8318 =

# Close Forward
# String
P8319 =

# Blind Transfer
# String
P8320 =

# Attended Transfer
# String
P8321 =

# Hold Call
# String
P8324 =

# UnHold Call
# String
P8325 =

##############################################################################
##  Settings/Affinity Settings
##############################################################################
# Affinity Support. 0 - Disabled, 1 - Enabled. Default is 0.
# Number: 0, 1
# Mandatory
P8334 = 0

# Preferred Account. 0 - Account 1, 1 - Account 2, 2 - Account 3, 3 - Account 4. Default is 0.
# Number: 0, 1, 2, 3
# Mandatory
P8335 = 0

#####################################################
##  Settings/E911 Service
#####################################################
# Enable E911. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1.
# Mandatory
P8565 = 0

# HELD Protocol. 0 - HTTP, 1 - HTTPS. Default is HTTP
# Number: 0, 1.
# Mandatory
P8566 = 0

# HELD Synchronization Interval. Default is 0
# Number: 0, 30 - 1440
# Mandatory
P8567 = 0

# Location Server
# String
P8568 =

# Location Server Username
# String
P8569 =

# Location Server Password
# String
P8570 =

# Secondary Location Server
# String
P8571 =

# Secondary Location Server Username
# String
P8572 =

# Secondary Location Server Password
# String
P8573 =

# HELD Location Types
# String
P8574 = geodetic,civic,locationURI

# HELD Use LLDP Information. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1.
# Mandatory
P8575 = 0

# HELD NAI. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1.
# Mandatory
P8576 = 0

# HELD Identity 1
# String
P8577 =

# HELD Identity Value 1
# String
P8578 =

# HELD Identity 2
# String
P8579 =

# HELD Identity Value 2
# String
P8580 =

# HELD Identity 3
# String
P8581 =

# HELD Identity Value 3
# String
P8582 =

# HELD Identity 4
# String
P8583 =

# HELD Identity Value 4
# String
P8584 =

# HELD Identity 5
# String
P8585 =

# HELD Identity Value 5
# String
P8586 =

# HELD Identity 6
# String
P8587 =

# HELD Identity Value 6
# String
P8588 =

# HELD Identity 7
# String
P8589 =

# HELD Identity Value 7
# String
P8590 =

# HELD Identity 8
# String
P8591 =

# HELD Identity Value 8
# String
P8592 =

# HELD Identity 9
# String
P8593 =

# HELD Identity Value 9
# String
P8594 =

# HELD Identity 10
# String
P8595 =

# HELD Identity Value 10
# String
P8596 =

# E911 Emergency Numbers. Default is 911
# String
P8597 = 911

# Geolocation-Routing Heade. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1.
# Mandatory
P8598 = 0

# Priority Header. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1.
# Mandatory
P8599 = 0

###################################################################
## Network/Basic Settings                                        ##
###################################################################

# Internet Protocol. 0 - Both IPv4 and IPv6, 1 - Both, prefer IPv6 , 2 - IPv4 Only, 3 - IPv6 Only. Default is 2.
# Number: 0, 1, 2, 3
# Mandatory
P1415 = 0

# IPv4 Address. 0 - DHCP, 1 - Static IP, 2 - PPPoE. Default is 0
# Number: 0, 1, 2
# Mandatory
# P8 = 0

##########################################
# DHCP
##########################################

# Host name, DHCP option 12. Max length allowed is 64 characters
# String
P146 =

# Vendor Class ID, DHCP option 60. Max length allowed is 64 characters
# String
# For GXP1780, Default value is Grandstream GXP1780.
# P148 = Grandstream GXP1780

# For GXP1782, Default value is Grandstream GXP1782.
# P148 = Grandstream GXP1782

# For GXP1760, Default value is Grandstream GXP1760.
# P148 = Grandstream GXP1760

##########################################
# PPPoE
##########################################

# PPPoE Account ID
# String
P82 =

# PPPoE Password
# String
# Cannot contain ` or "
P83 =

# PPPoE Service Name
# String
P269 =

##########################################
# Statically Configured
##########################################

# IP Address. Ignore if DHCP or PPPoE is used
# Number: 0 - 255
P9 = 192

# Number: 0 - 255
P10 = 168

# Number: 0 - 255
P11 = 0

# Number: 0 - 255
P12 = 160

# Subnet mask. Ignore if DHCP or PPPoE is used
# Number: 0 - 255
P13 = 255

# Number: 0 - 255
P14 = 255

# Number: 0 - 255
P15 = 0

# Number: 0 - 255
P16 = 0

# Gateway. Ignore if DHCP or PPPoE is used
# Number: 0 - 255
P17 = 0

# Number: 0 - 255
P18 = 0

# Number: 0 - 255
P19 = 0

# Number: 0 - 255
P20 = 0

# DNS 1 Server. Ignore if DHCP or PPPoE is used
# Number: 0 - 255
P21 = 0

# Number: 0 - 255
P22 = 0

# Number: 0 - 255
P23 = 0

# Number: 0 - 255
P24 = 0

# DNS 2 Server. Ignore if DHCP or PPPoE is used
# Number: 0 - 255
P25 = 0

# Number: 0 - 255
P26 = 0

# Number: 0 - 255
P27 = 0

# Number: 0 - 255
P28 = 0

# Preferred DNS server.  (if specified).
# Number: 0 - 255
P92 = 0

# Number: 0 - 255
P93 = 0

# Number: 0 - 255
P94 = 0

# Number: 0 - 255
P95 = 0

# IPv6 Address. 0 - Auto Configured, 1 - Statically Configured. Default is 0.
# Number: 0, 1
# Mandatory
P1419 = 0

# IPv6 Address type: Statically configured. 0 - Full Static, 1 - Prefix Static
# Number: 0, 1
P1426 = 0

# Full Static: Static IPv6 Address
P1420 =

# Full Static: IPv6 Prefix Length
P1421 =

# Prefix Static: IPv6 Prefix (64bits)
P1422 =

# DNS Server 1 for IPv6 Address
# String: a-z, A-Z, 0-9, ".", ":", "[", "]"
P1424 =

# DNS Server 2 for IPv6 Address
# String: a-z, A-Z, 0-9, ".", ":", "[", "]"
P1425 =

# Preferred DNS Server for IPv6 Address
# String: a-z, A-Z, 0-9, ".", ":", "[", "]"
P1423 =

##############################################################################
## Network/Advanced Settings                                                ##
##############################################################################
# 802.1X Mode. 0 - Disable, 1 - EAP-MD5. 2 - EAP-TLS, 3 - EAP-PEAPv0/MSCHAPv2. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P7901 = 0

# 802.1X Identity. Max length allowed is 64 characters
# String
P7902 =

# MD5 Password. Max length allowed is 64 characters
# String
P7903 =

# 802.1X CA Certificate
# String
# P8439 =

# 802.1X Client Certificate
# String
# P8440 =

# HTTP Proxy
# String
P1552 =

# HTTPS Proxy
# String
P1553 =

# Layer 3 QoS for SIP
# Number:0 - 63
# Mandatory
P1558 = 26

# Layer 3 QoS for RTP
# Number:0 - 63
# Mandatory
P1559 = 46

# Layer 2 QoS 802.1Q/VLAN Tag (VLAN classification for RTP). Default is 0
# Number: 0 - 4094
# Mandatory
P51 = 0

# Layer 2 QoS 802.1p Priority Value (0 - 7). Default is 0
# Number: 0 - 7
# Mandatory
P87 = 0

# PC Port Mode. 0 - Enable, 1 - Disabled, 2 - Mirrored. Default is 0
# Number: 0, 1, 2
# Mandatory
P1348 = 0

# PC Port VLAN Tag. Default is 0
# Number: 0 - 4094
#Mandatory
P229 = 0

# PC Port Priority Value. Default is 0
# Number: 0 - 7
# Mandatory
P230 = 0

# Enable LLDP.  0 - Disabled, 1 - Enabled. Default is 1
# Number: 0, 1
# Mandatory
P1684 = 1

###############################################################
# Network/Remote Control
###############################################################
# CSTA Control
# Number: 0 - Disabled, 1 - Enabled. Default is 0.
# Mandatory
P32053 = 0

##############################################################################
## Network/OpenVPN Settings                                                 ##
##############################################################################
# OpenVPN Enable.  0 - No, 1 - Yes. Default is 1
#Number: 0, 1
#Mandatory
P7050 = 0

# OpenVPN Server Address
# String
P7051 =

# OpenVPN Port. Default is 1194
# Number
# Mandatory
P7052 = 1194

# OpenVPN Transport.  0 - UDP, 1 - TCP. Default is 0
#Number: 0, 1
#Mandatory
P2912 = 0

# OpenVPN CA.
# Strings
P9902 =

# OpenVPN Certificate.
# Strings
P9903 =

# OpenVPN Client Key.
# Strings
P9904 =

###############################################################
# Network/WiFi Settings o<Only for GXP1760W)
###############################################################
# Enable / Disable Wifi. Default is 0.
# Number: 0, 1
# Mandatory
P7800 = 0

## Access Point 1

# SSID
# String
P8403 =

# Password
# String
P8404 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8405 = 0

## Access Point 2

# SSID
# String
P8406 =

# Password
# String
P8407 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8408 = 0

## Access Point 3

# SSID
# String
P8409 =

# Password
# String
P8410 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8411 = 0

## Access Point 4

# SSID
# String
P8412 =

# Password
# String
P8413 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8414 = 0

## Access Point 5

# SSID
# String
P8415 =

# Password
# String
P8416 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8417 = 0

## Access Point 6

# SSID
# String
P8418 =

# Password
# String
P8419 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8420 = 0

## Access Point 7

# SSID
# String
P8421 =

# Password
# String
P8422 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8423 = 0

## Access Point 8

# SSID
# String
P8424 =

# Password
# String
P8425 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8426 = 0

## Access Point 9

# SSID
# String
P8427 =

# Password
# String
P8428 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8429 = 0

## Access Point 10

# SSID
# String
P8430 =

# Password
# String
P8431 =

# Security Type. Default is 0.
# Number: 0, 1, 4, 5, 8
# Mandatory
P8432 = 0

# Country. Default is AL. (US Model does not have this)
# String: (Country Code)
# Mandatory
# P7831 = AL

##############################################################################
##  Maintenance/Web Access                                                 ##
##############################################################################
# Enable User Web Access. 0 - Disabled, 1 - Enabled. Default is 0
# Number: 0, 1
# Mandatory
P8469 = 0

# End User Password
# String: a-z, A-Z, 0-9
# P196 =

# Admin password for web interface
# String: a-z, A-Z, 0-9
# P2 =

##############################################################################
##  Maintenance/Upgrade and Provisioning
##############################################################################
# Firmware Upgrade and Provisioning
# 0 - Always Check For New Firmware
# 1 - Check New Firmware only when F/W pre/suffix changes
# 2 - Always Skip the Firmware Check
# Number: 0, 1, 2
# Mandatory
P238 = 0

# Always Authenticate Before Challenge. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P20713 = 0

# Allow DHCP Option 43 and Option 66 to override server. 0 - No, 1 - Yes. Default is 1
# When set to Yes(1), it will override the configured provision path and method
# Number: 0, 1
# Mandatory
P145 = 1

# Additional Override DHCP Option. 0 - None,  1 - Option 150,  2 - Option 160. Default is 0
# Number: 0, 1, 2
# Mandatory
P8337 = 0

# Allow DHCP Option 120 to Override SIP Server.
# 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1411 = 0

# 3CX Auto Provision. 0 - No, 1 - Yes. Default is Yes
# Number: 0, 1
# Mandatory
P1414 = 1

# Automatic Upgrade.
# 0 - No,
# 1 - Yes, check for upgrade based on minute(s) setting,
# 2 - Yes, check for upgrade based on Hour of Day setting,
# 3 - Yes, check for upgrade based on Day of Week setting,
# Default is No
# Number: 0, 1, 2, 3
# Mandatory
P194 = 0

# Check for new firmware every () minutes. Default is 7 days.
# Number: 60-86400
# Mandatory
P193 = 1008

# Randomize Automatic Upgrade
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P8458 = 0

# Hour of the Day--Start (0-23). Default is 1
# Number: 0 - 23
# Mandatory
P285 = 1

# Hour of the Day--End (0-23). Default is 0
# Number: 0 - 23
P8459 = 0

# Day of the Week (0-6). Default is 1
# Number: 0-6
P286 = 1

# Disable SIP NOTIFY Authentication. 0 - No, 1 - Yes. Default is 0
# Number: 0,1
# Mandatory
P4428 = 0

# Config
# Config Upgrade Via. 0 - TFTP, 1 - HTTP, 2 - HTTPS. Default is 1
# Number: 0,1
# Mandatory
P212 = 1

# Config Server Path
# String
P237 = fm.grandstream.com/gs

# Config HTTP/HTTPS User Name
# String
P1360 =

# Config HTTP/HTTPS Password
# String
P1361 =

# Config File Prefix
# String
P234 =

# Config File Postfix
# String
P235 =

# XML Config File Password
# String
P1359 =

# Authenticate Conf File. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P240 = 0

# Firmware
# Firmware Upgrade Via. 0 - TFTP, 1 - HTTP, 2 - HTTPS. Default is 1
# Number: 0,1
# Mandatory
P6767 = 1

# Firmware Server Path
# String
P192 = fm.grandstream.com/gs

# Firmware HTTP/HTTPS User Name.
# String
P6768 =

# Firmware HTTP/HTTPS Password.
# String
P6769 =

# Firmware File Prefix
# String
P232 =

# Firmware File Postfix
# String
P233 =

##############################################################################
##  Maintenance/Syslog
##############################################################################
# Syslog Protocol
# 0 - UDP, 1 - SSL/TLS. Default is 0
# Number: 0, 1
# Mandatory
P8402 = 0

# Syslog Server (name of the server, max length is 64 characters)
# String
P207 =

# Syslog Level. 0 - NONE, 1 - DEBUG, 2 - INFO, 3 - WARNING, 4 - ERROR. Default is 0
# Number: 0, 1 , 2, 3, 4
# Mandatory
P208 = 0

# Send SIP Log. 0 - Do not send SIP log in Syslog, 1 - Send SIP log in Syslog if configured and set to DEBUG level. Default is 0
# Number: 0, 1
# Mandatory
P1387 = 0

# Auto Recover from Abnormal. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P1438 = 1

# USB Console Log. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P2922 = 0

##############################################################################
##  Maintenance/Language
##############################################################################
# Display Language. Default is Auto
# ar - Arabic, cz - Czech, de - Deutsh
# en - English, es - Spanish, fr - Francais
# he - Hebrew, hr - Hrvatski, hu - Magyar
# it - Italiano, ja - japanese, ko - korean, lv - latvian
# nl - Dutch, pl - Polski, pt - Portugue
# ru - Russian, sl - Slovenian, se - Svenska, tr - Turkish
# zh-tw - traditional chinese, zh - simplified chinese, auto - Automatic
# gxp - Downloaded Language
# String
# Mandatory
P1362 = Auto

##############################################################################
##  Maintenance/TR-069
##############################################################################
# ACS URL
# String
# P4503 =

# TR-069 Username
# String
P4504 =

# TR-069 Password
# String
P4505 =

# Periodic Inform Enable. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P4506 = 0

# Periodic Inform Interval. Default is 86400.
# Number
P4507 = 86400

# Connection Request Username
# String: a-z, A-Z, 0-9
P4511 =

# Connection Request Password
# String: a-z, A-Z, 0-9
P4512 =

# Connection Request Port. Default is 7547.
# Number
P4518 = 7547

# CPE SSL Certificate
# String: a-z, A-Z, 0-9
P8220 =

# CPE SSL Private Key
# String: a-z, A-Z, 0-9
P8221 =

##############################################################################
##  Maintenance/Security Settings/Security                                  ##
##############################################################################
# Configuration Via Keypad Menu. 0 - Unrestricted, 1 - Basic settings only, 2 - Constraint mode, 3 - Locked Mode. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P1357 = 0

# Enable STAR key Keypad locking. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P1382 = 0

# Password to Lock/Unlock (0-9 only)
# Number
P1383 =

# Validate Server Certificates
# Number: 0 - No, 1 - Yes. Default is 0.
# Mandatory
P8463 = 0

# SIP TLS Certificate
# String: a-z, A-Z, 0-9
#P280 =

# SIP TLS Private Key
# String: a-z, A-Z, 0-9
#P279 =

# SIP TLS Private Key Password
# String: a-z, A-Z, 0-9
P281 =

# Custom Certificate
# String
#P8472 =

# Web Access Mode. 0 - HTTPS, 1 - HTTP, 2 - Disabledo< 3 - Both HTTP and HTTPS. Default is 1
# Number: 0, 1, 2
# Mandatory
P1650 = 1

# Disable SSH. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
P276 = 0

# Web/Keypad/Restrict mode Lockout Duration (0-60 minutes). Default is 5
# Number: 0-60
P1683 = 5

# Minimum TLS Version. 10 - TLS 1.0, 11 - TLS 1.1, 12 - TLS 1.2. Default is 11
# Number: 10, 11, 12
# Mandatory
P22293 = 11

# Maximum TLS Version. 99 - Unlimited, 10 - TLS 1.0, 11 - TLS 1.1, 12 - TLS 1.2. Default is 99
# Number: 99, 10, 11, 12
# Mandatory
P22294 = 99

# Enable/Disable Weak Ciphers. Default is 0
# 0 - Enable Weak TLS Ciphers Suites
# 1 - Disable Symmetric Encryption RC4/DES/3DES
# 2 - Disable Symmetric Encryption SEED
# 3 - Disable All Weak Symmetric Encryption
# 4 - Disable Symmetric Authentication MD5
# 5 - Disable All Weak TLS Cipher Suites
# Number: 0 - 5
P8536 = 0

##############################################################################
##  Maintenance/Security Settings/Trusted CA Certificates                   ##
##############################################################################
# Trust CA Certificate
# String
# P8433 =
# P8434 =
# P8435 =
# P8436 =
# P8437 =
# P8438 =

# Load CA Certificates. 0 - Default Certificates, 1 - Custom Certificates, 2 - All Certificates. Default is 2
# Number: 0, 1, 2
# Mandatory
P8502 = 2

##############################################################################
##  Maintenance/Packet Capture                                              ##
##############################################################################
# Capture Location. 0 - Internal Storage, 1 - USB. Default is 0
# Number: 0, 1.
# Mandatory
P2999 = 0

# With RTP Packets. 0 - No, 1 - Yes. Default is 0
# Number: 0, 1
# Mandatory
P6007 = 0

# USB Filename
# String
P6008 =

##############################################################################
##  Phonebook/Phonebook Management
##############################################################################
# Enable Phonebook XML Download.
# 0 - Disabled, 1 - Enabled, use HTTP, 2 - Enabled, use TFTP, 3 - Enabled, use HTTPS. Default is 0
# Number: 0, 1, 2, 3
# Mandatory
P330 = 0

# HTTP/HTTPS User Name
# String
P6713 =

# HTTP/HTTPS Password.
# String
P6714 =

# Phonebook XML Server Path
# This is a string of up to 256 characters that should contain a path to the XML file. It MUST be in the host/path format.
# For example: directory.grandstream.com/engineering
# String
P331 =

# Phonebook Download Interval (in minutes)
# Valid value range is 5-720. Default is 0 for disabled
# Number: 0, 5-720;
P332 = 0

# Remove Manually-edited Entries on Download. 0 - No, 1 - Yes. Default is 1
# Number: 0, 1
# Mandatory
P333 = 1

# Import Group Method. 0 - Replace, 1 - Append. Default is 0
# Number: 0, 1
# Mandatory
P8462 = 0

# Sort Phonebook by. 0 - Last Name, 1 - First Name. Default is 0
# Number: 0, 1
# Mandatory
P2914 = 0

# Phonebook Key Function. 0 - Default, 1 - LDAP Search, 2 - Local Phonebook, 3 - Local Group, 4 - Broadsoft Phonebook
# Number: 0, 1, 2, 3, 4.
# Mandatory
P1526 = 0

##############################################################################
##  Phonebook/LDAP
##############################################################################
# LDAP Server Address, up to 256 characters can be used. It can be IP address or Domain name
# String
P8020 =

# LDAP Server Port. Default is 389.
# Number: 1 - 65535
P8021 = 389

# Base. The LDAP search base refers to the location in the directory where the search is requested to begin
# Example 1: dc=grandstream,dc=com
# Example 2: ou=Boston, dc=grandstream,dc=com
# String
P8022 =

# User name. The bind "Username" for querying LDAP servers. Some LDAP servers allow anonymous binds in which case the setting can be left blank
# String
P8023 =

# Password. The bind "Password" for querying LDAP servers. And the field can be left blank if the LDAP server allows anonymous binds
# String
P8024 =

# LDAP Number Filter
# LDAP name filter is the filter used for name look ups. Please refer to user manual for examples
# String
P8025 =

# LDAP Name Filter
# LDAP name filter is the filter used for name look ups. Please refer to user manual for examples
# String
P8026 =

# LDAP Version. Protocol version for the phone when send the bind requests
# 2 - version 2, 3 - version 3. Default is 3
# Mandatory
P8027 = 3

# LDAP Name Attributes. This setting specifies the "name" attributes of each record which are returned in the LDAP search result
# The setting allows the users to configure multiple space separated name attributes
# String
P8028 =

# LDAP Number Attributes. This setting specifies the bnumberb attributes of each record which are returned in the LDAP search result
# The setting allows the users to configure multiple space separated number attributes
# String
P8029 =

# LDAP Display Name. The entry information to be shown on phone LCD. Grandstream phones will display up to 3 fields
# String
P8030 =

# Max Hits. The setting specifies the maximum number of results to be returned by the LDAP server
# If the value is set to 0, server will return all search results. Default is 50
# Number: 0 - 32000
# Mandatory
P8031 = 50

# Search Timeout (in seconds). Default is 30
# The setting specifies how long the server should process the request and client waits for server to return
# Number: 0 - 180
# Mandatory
P8032 = 30

# Sort Results. This setting is used for sorting searching result. Default is 0
# Number: 0, 1. 0 - No, 1 - Yes. Default is 0
# Mandatory
P8033 = 0

# LDAP Lookup
# Incoming calls. 0 - No, 1 - Yes. Default is 0
P8035 = 0

# Outgoing calls. 0 - No, 1 - Yes. Default is 0
P8034 = 0

# Lookup Display Name
# String
P8036 =
PVALUES;

    $pValues = [];
    $pComments = [];
    $pendingComments = [];

    foreach (preg_split('/\R/', $vendorDefaults) as $assignment) {
        if (preg_match('/^P(\d+)\s*=\s?(.*)$/', $assignment, $match)) {
            $code = 'P' . $match[1];
            $pValues[$code] = trim($match[2]);
            $pComments[$code] = $pendingComments;
            $pendingComments = [];
            continue;
        }

        if (preg_match('/^#\s*P(\d+)\s*=/', $assignment, $match)) {
            $pComments['P' . $match[1]] = $pendingComments;
            $pendingComments = [];
            continue;
        }

        if (str_starts_with($assignment, '#')) {
            if (preg_match('/^#{3,}\s*$/', $assignment)) {
                $lastIndex = array_key_last($pendingComments);
                $previousIndex = $lastIndex !== null ? $lastIndex - 1 : null;
                if (
                    $lastIndex !== null
                    && $previousIndex !== null
                    && isset($pendingComments[$previousIndex])
                    && str_starts_with($pendingComments[$previousIndex], '###')
                    && !str_starts_with($pendingComments[$lastIndex], '##')
                ) {
                    $pendingComments[$lastIndex] = '## ' . trim($pendingComments[$lastIndex], " #\t\n\r\0\x0B") . ' ##';
                }

                $comment = str_repeat('#', 70);
            } elseif (preg_match('/^#+\s*(.*?)\s*##\s*$/', $assignment, $heading)) {
                $comment = '## ' . trim($heading[1]) . ' ##';
            } elseif (str_starts_with($assignment, '##')) {
                $comment = trim($assignment);
            } else {
                $comment = trim(preg_replace('/^#\s?/', '', $assignment));
            }

            if ($comment !== '') {
                $pendingComments[] = str_replace('--', '- -', $comment);
            }
        }
    }

    $set = static function (string $code, mixed $value) use (&$pValues): void {
        $pValues[$code] = (string) ($value ?? '');
    };

    $transportMap = ['udp' => '0', 'tcp' => '1', 'tls' => '2', 'tls or tcp' => '2'];
    $dialPlan = $settings['grandstream_dial_plan'] ?? '{ x+ | \+x+ | *x+ | *xx*x+ }';
    $lineByNumber = collect($lines)->keyBy(fn ($line) => (int) ($line['line_number'] ?? 0));

    // System, web access, time, and locale.
    $webCredentials = [];
    if (array_key_exists('admin_password', $settings)) {
        $webCredentials['P2'] = (string) $settings['admin_password'];
    }
    if (array_key_exists('user_password', $settings)) {
        $webCredentials['P196'] = (string) $settings['user_password'];
    }
    $pValues = $webCredentials + $pValues;

    $set('P8469', '1'); // Enable end-user web access.
    $set('P1650', '3'); // Allow both HTTP and HTTPS web access.
    $set('P8468', '0'); // Do not reject provisioned passwords using the strength prompt.
    $set('P30', $settings['ntp_server_primary'] ?? 'pool.ntp.org');
    $set('P8333', $settings['ntp_server_secondary'] ?? '');
    $set('P64', $settings['grandstream_time_zone'] ?? 'auto');
    $set('P246', 'MTZ+6MDT+5,M3.2.0,M11.1.0');

    $dateFormatMap = [
        'yyyy-mm-dd' => '0',
        'mm-dd-yyyy' => '1',
        'dd-mm-yyyy' => '2',
        'dddd, mmmm dd' => '3',
        'mmmm dd, dddd' => '4',
    ];
    $dateFormat = strtolower((string) ($settings['grandstream_format_date'] ?? 'yyyy-mm-dd'));
    $set('P102', $dateFormatMap[$dateFormat] ?? (is_numeric($dateFormat) ? $dateFormat : '0'));

    $timeFormat = strtolower((string) ($settings['grandstream_format_time'] ?? '12hour'));
    $set('P122', in_array($timeFormat, ['1', '24', '24hour', '24 hour'], true) ? '1' : '0');

    // Upgrade, provisioning, and diagnostics.
    $set('P238', '0');
    $set('P145', '0');
    $set('P1411', '0');
    $set('P1414', '1');
    $set('P194', $settings['grandstream_automatic_provisioning'] ?? '0');
    $set('P193', '10080');
    $set('P285', '1');
    $set('P286', '1');
    $set('P212', '2');
    $set('P237', trim(str_replace(['http://', 'https://'], '', $settings['provision_base_url'] ?? ''), " /"));
    $set('P1360', $settings['http_auth_username'] ?? '');
    $set('P1361', $settings['http_auth_password'] ?? '');
    $set('P240', '0');
    $set('P6767', $settings['grandstream_firmware_upgrade_protocol'] ?? '2');
    $set('P192', trim(str_replace(['http://', 'https://'], '', $settings['grandstream_firmware_path'] ?? 'fm.grandstream.com/gs'), " /"));
    $set('P207', $settings['grandstream_syslog_server'] ?? '');
    $set('P208', $settings['grandstream_syslog_level'] ?? '0');
    $set('P1387', $settings['grandstream_send_sip_log'] ?? '0');

    // Shared phone behavior matching the established Grandstream templates.
    $set('P2909', 'f1=540,f2=516,c=70/16-55/16-70/300;');
    $callWaiting = strtolower((string) ($settings['grandstream_call_waiting'] ?? 'no'));
    $set('P91', in_array($callWaiting, ['1', 'yes', 'true'], true) ? '1' : '0');

    if (!empty($settings['grandstream_phonebook_server'])) {
        $set('P330', str_starts_with(strtolower((string) $settings['grandstream_phonebook_server']), 'https://') ? '3' : '1');
        $set('P331', trim(str_replace(['http://', 'https://'], '', $settings['grandstream_phonebook_server']), " /"));
        $set('P332', $settings['grandstream_phonebook_download_interval'] ?? '60');
        $set('P6713', $settings['grandstream_phonebook_username'] ?? '');
        $set('P6714', $settings['grandstream_phonebook_password'] ?? '');
    }

    $accountCodes = [
        1 => ['active' => 'P271', 'account' => 'P270', 'server' => 'P47',  'secondary' => 'P2312', 'outbound' => 'P48',  'backupOutbound' => 'P2333', 'blf' => 'P2375', 'user' => 'P35',  'auth' => 'P36',  'password' => 'P34',  'name' => 'P3',   'voicemail' => 'P33',  'dns' => 'P103', 'nat' => 'P52',  'register' => 'P31',  'expires' => 'P32',  'subscribe' => 'P26051', 'keepAlive' => 'P2397', 'keepInterval' => 'P2398', 'keepMaxLost' => 'P2399', 'localPort' => 'P40',  'retry' => 'P138', 'transport' => 'P130', 'mwi' => 'P99',  'srtp' => 'P183', 'dialPlan' => 'P290', 'features' => 'P191', 'xGrandstream' => 'P26054', 'pani' => 'P26058', 'pei' => 'P26059'],
        2 => ['active' => 'P401', 'account' => 'P417', 'server' => 'P402', 'secondary' => 'P2412', 'outbound' => 'P403', 'backupOutbound' => 'P2433', 'blf' => 'P2475', 'user' => 'P404', 'auth' => 'P405', 'password' => 'P406', 'name' => 'P407', 'voicemail' => 'P426', 'dns' => 'P408', 'nat' => 'P414', 'register' => 'P410', 'expires' => 'P412', 'subscribe' => 'P26151', 'keepAlive' => 'P2497', 'keepInterval' => 'P2498', 'keepMaxLost' => 'P2499', 'localPort' => 'P413', 'retry' => 'P471', 'transport' => 'P448', 'mwi' => 'P415', 'srtp' => 'P443', 'dialPlan' => 'P459', 'features' => 'P420', 'xGrandstream' => 'P26154', 'pani' => 'P26158', 'pei' => 'P26159'],
        3 => ['active' => 'P501', 'account' => 'P517', 'server' => 'P502', 'secondary' => 'P2512', 'outbound' => 'P503', 'backupOutbound' => 'P2533', 'blf' => 'P2575', 'user' => 'P504', 'auth' => 'P505', 'password' => 'P506', 'name' => 'P507', 'voicemail' => 'P526', 'dns' => 'P508', 'nat' => 'P514', 'register' => 'P510', 'expires' => 'P512', 'subscribe' => 'P26251', 'keepAlive' => 'P2597', 'keepInterval' => 'P2598', 'keepMaxLost' => 'P2599', 'localPort' => 'P513', 'retry' => 'P571', 'transport' => 'P548', 'mwi' => 'P515', 'srtp' => 'P543', 'dialPlan' => 'P559', 'features' => 'P520', 'xGrandstream' => 'P26254', 'pani' => 'P26258', 'pei' => 'P26259'],
        4 => ['active' => 'P601', 'account' => 'P617', 'server' => 'P602', 'secondary' => 'P2612', 'outbound' => 'P603', 'backupOutbound' => 'P2633', 'blf' => 'P2675', 'user' => 'P604', 'auth' => 'P605', 'password' => 'P606', 'name' => 'P607', 'voicemail' => 'P626', 'dns' => 'P608', 'nat' => 'P614', 'register' => 'P610', 'expires' => 'P612', 'subscribe' => 'P26351', 'keepAlive' => 'P2697', 'keepInterval' => 'P2698', 'keepMaxLost' => 'P2699', 'localPort' => 'P613', 'retry' => 'P671', 'transport' => 'P648', 'mwi' => 'P615', 'srtp' => 'P643', 'dialPlan' => 'P659', 'features' => 'P620', 'xGrandstream' => 'P26354', 'pani' => 'P26358', 'pei' => 'P26359'],
    ];

    foreach ($accountCodes as $number => $codes) {
        $line = $lineByNumber->get($number);
        $set($codes['active'], $line ? '1' : '0');

        if (!$line) {
            continue;
        }

        $transport = $transportMap[strtolower((string) ($line['sip_transport'] ?? 'tcp'))] ?? '1';
        $srtp = strtolower((string) ($line['sip_transport'] ?? '')) === 'tls or tcp' ? '1' : '0';
        $displayName = $line['display_name'] ?? $line['auth_id'] ?? '';
        $registerExpires = max(1, (int) (($line['register_expires'] ?? 3600) / 60));
        $outboundProxy = trim((string) ($line['outbound_proxy_primary'] ?? ''));
        $backupOutboundProxy = trim((string) ($line['outbound_proxy_secondary'] ?? ''));
        $sipPort = trim((string) ($line['sip_port'] ?? ''));

        $set($codes['account'], $displayName);
        $set($codes['server'], $line['server_address'] ?? '');
        $set($codes['secondary'], $line['server_address_secondary'] ?? '');
        $set($codes['outbound'], $outboundProxy . ($outboundProxy !== '' && $sipPort !== '' ? ':' . $sipPort : ''));
        $set($codes['backupOutbound'], $backupOutboundProxy . ($backupOutboundProxy !== '' && $sipPort !== '' ? ':' . $sipPort : ''));
        $set($codes['blf'], '');
        $set($codes['user'], $line['auth_id'] ?? '');
        $set($codes['auth'], $line['auth_id'] ?? '');
        $set($codes['password'], $line['password'] ?? '');
        $set($codes['name'], $displayName);
        $set($codes['voicemail'], $settings['voicemail_number'] ?? '');
        $set($codes['dns'], $settings['grandstream_dns_mode'] ?? '0');
        $set($codes['nat'], '2');
        $set($codes['register'], '1');
        $set($codes['expires'], $registerExpires);
        $set($codes['subscribe'], '60');
        $set($codes['keepAlive'], '1');
        $set($codes['keepInterval'], '30');
        $set($codes['keepMaxLost'], '3');
        $set($codes['localPort'], 5060 + (($number - 1) * 2));
        $set($codes['retry'], '20');
        $set($codes['transport'], $transport);
        $set($codes['mwi'], '1');
        $set($codes['srtp'], $srtp);
        $set($codes['dialPlan'], $dialPlan);
        $set($codes['features'], '0');
        $set($codes['xGrandstream'], '1');
        $set($codes['pani'], '1');
        $set($codes['pei'], '1');
    }

    $vpkModeMap = [
        'none' => '-1',
        'line' => '0',
        'sharedline' => '1',
        'speed dial' => '10',
        'blf' => '11',
        'presence watcher' => '12',
        'eventlist blf' => '13',
        'speed dial via active account' => '14',
        'dial dtmf' => '15',
        'voicemail' => '16',
        'transfer' => '18',
        'call park' => '19',
        'intercom' => '20',
        'monitored call park' => '26',
    ];

    $vpkCodes = [
        1 => ['mode' => 'P1363', 'account' => 'P1364', 'label' => 'P1465', 'value' => 'P1466'],
        2 => ['mode' => 'P1365', 'account' => 'P1366', 'label' => 'P1467', 'value' => 'P1468'],
        3 => ['mode' => 'P1367', 'account' => 'P1368', 'label' => 'P1469', 'value' => 'P1470'],
        4 => ['mode' => 'P1369', 'account' => 'P1370', 'label' => 'P1471', 'value' => 'P1472'],
        5 => ['mode' => 'P1371', 'account' => 'P1372', 'label' => 'P1473', 'value' => 'P1474'],
        6 => ['mode' => 'P1373', 'account' => 'P1374', 'label' => 'P1475', 'value' => 'P1476'],
    ];

    for ($slot = 7; $slot <= 32; $slot++) {
        $base = 23800 + (($slot - 7) * 4);
        $vpkCodes[$slot] = ['mode' => 'P' . $base, 'account' => 'P' . ($base + 1), 'label' => 'P' . ($base + 2), 'value' => 'P' . ($base + 3)];
    }

    $mainKeyById = collect($main_keys)->keyBy(fn ($key) => (int) ($key['id'] ?? 0));
    foreach ($vpkCodes as $slot => $codes) {
        $key = $mainKeyById->get($slot);
        $type = strtolower(trim((string) ($key['type'] ?? 'none')));
        $set($codes['mode'], $vpkModeMap[$type] ?? '-1');
        $set($codes['account'], max(0, (int) ($key['line'] ?? 0)));
        $set($codes['label'], $key['label'] ?? '');
        $set($codes['value'], $key['value'] ?? '');
    }
@endphp
<?xml version="1.0" encoding="UTF-8" ?>
<!-- Grandstream GXP1782/1780/1760 XML provisioning configuration -->
<gs_provision version="1">
  <config version="1">
@foreach ($pValues as $code => $value)
@foreach (($pComments[$code] ?? []) ?: ["P-value {$code}."] as $comment)
    <!-- {{ $comment }} -->
@endforeach
    <{{ $code }}>{{ $value }}</{{ $code }}>
@endforeach
  </config>
</gs_provision>
@break

@endswitch
