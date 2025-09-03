{{-- version: 1.0.0 --}}

@switch($flavor)

{{-- ================= Poly mac.cfg ================= --}}
@case('mac.cfg')

<?xml version="1.0" standalone="yes"?>
<APPLICATION
    CONFIG_FILES="phone[PHONE_MAC_ADDRESS].cfg" 
    MISC_FILES="" 
    LOG_FILE_DIRECTORY="" 
    OVERRIDES_DIRECTORY="" 
    CONTACTS_DIRECTORY="" 
    LICENSE_DIRECTORY="" 
    USER_PROFILES_DIRECTORY="" 
    CALL_LISTS_DIRECTORY="">


  <APPLICATION_SPIP300 APP_FILE_PATH_SPIP300="sip_213.ld" CONFIG_FILES_SPIP300="phone1_213.cfg, sip_213.cfg" />
  <APPLICATION_SPIP500 APP_FILE_PATH_SPIP500="sip_213.ld" CONFIG_FILES_SPIP500="phone1_213.cfg, sip_213.cfg" />
  <APPLICATION_SPIP301 APP_FILE_PATH_SPIP301="sip_318.ld" CONFIG_FILES_SPIP301="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP501 APP_FILE_PATH_SPIP501="sip_318.ld" CONFIG_FILES_SPIP501="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP600 APP_FILE_PATH_SPIP600="sip_318.ld" CONFIG_FILES_SPIP600="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP601 APP_FILE_PATH_SPIP601="sip_318.ld" CONFIG_FILES_SPIP601="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP430 APP_FILE_PATH_SPIP430="sip_327.ld" CONFIG_FILES_SPIP430="phone1_327.cfg, sip_327.cfg" />
  <APPLICATION_SPIP320 APP_FILE_PATH_SPIP320="sip_335.ld" CONFIG_FILES_SPIP320="" />
  <APPLICATION_SPIP330 APP_FILE_PATH_SPIP330="sip_335.ld" CONFIG_FILES_SPIP330="" />
  <APPLICATION_SPIP321 APP_FILE_PATH_SPIP321="sip_40x.ld" CONFIG_FILES_SPIP321="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP331 APP_FILE_PATH_SPIP331="sip_40x.ld" CONFIG_FILES_SPIP331="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP335 APP_FILE_PATH_SPIP335="sip_40x.ld" CONFIG_FILES_SPIP335="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP450 APP_FILE_PATH_SPIP450="sip_40x.ld" CONFIG_FILES_SPIP450="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP550 APP_FILE_PATH_SPIP550="sip_40x.ld" CONFIG_FILES_SPIP550="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP560 APP_FILE_PATH_SPIP560="sip_40x.ld" CONFIG_FILES_SPIP560="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP650 APP_FILE_PATH_SPIP650="sip_40x.ld" CONFIG_FILES_SPIP650="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP670 APP_FILE_PATH_SPIP670="sip_40x.ld" CONFIG_FILES_SPIP670="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP4000 APP_FILE_PATH_SSIP4000="sip_318.ld" CONFIG_FILES_SSIP4000="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SSIP5000 APP_FILE_PATH_SSIP5000="sip_40x.ld" CONFIG_FILES_SSIP5000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP6000 APP_FILE_PATH_SSIP6000="sip_40x.ld" CONFIG_FILES_SSIP6000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP7000 APP_FILE_PATH_SSIP7000="sip_40x.ld" CONFIG_FILES_SSIP7000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSDuo APP_FILE_PATH_SSDuo="sip_40x.ld" CONFIG_FILES_SSDuo="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX300 APP_FILE_PATH_VVX300="sip_59x.ld" CONFIG_FILES_VVX300="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX310 APP_FILE_PATH_VVX310="sip_59x.ld" CONFIG_FILES_VVX310="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX400 APP_FILE_PATH_VVX400="sip_59x.ld" CONFIG_FILES_VVX400="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX410 APP_FILE_PATH_VVX410="sip_59x.ld" CONFIG_FILES_VVX410="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX500 APP_FILE_PATH_VVX500="sip_59x.ld" CONFIG_FILES_VVX500="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX600 APP_FILE_PATH_VVX600="sip_59x.ld" CONFIG_FILES_VVX600="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX1500 APP_FILE_PATH_VVX1500="sip_59x.ld" CONFIG_FILES_VVX1500="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  
</APPLICATION>
@break

{{-- ================= Poly phonemac.cfg ================= --}}
@case('phonemac.cfg')

<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<lineKey
  lineKey.reassignment.enabled="1"
  
  lineKey.1.index="1"
  lineKey.1.category="{{FunctionkeyType_1}} ?? 'line'"
  lineKey.2.index="2"
    lineKey.2.category="{{FunctionkeyType_2}}"
  lineKey.3.index="3"
    lineKey.3.category="{{FunctionkeyType_3}}"
  lineKey.4.index="4"
    lineKey.4.category="{{FunctionkeyType_4}}"
  lineKey.5.index="5"
    lineKey.5.category="{{FunctionkeyType_5}}"
  lineKey.6.index="6"
    lineKey.6.category="{{FunctionkeyType_6}}"
  lineKey.7.index="7"
    lineKey.7.category="{{FunctionkeyType_7}}"
  lineKey.8.index="8"
    lineKey.8.category="{{FunctionkeyType_8}}"
  lineKey.9.index="9"
    lineKey.9.category="{{FunctionkeyType_9}}"
  lineKey.10.index="10"
    lineKey.10.category="{{FunctionkeyType_10}}"
  lineKey.11.index="11"
    lineKey.11.category="{{FunctionkeyType_11}}"
  lineKey.12.index="12"
    lineKey.12.category="{{FunctionkeyType_12}}"
  lineKey.13.index="13"
    lineKey.13.category="{{FunctionkeyType_13}}"
  lineKey.14.index="14"
    lineKey.14.category="{{FunctionkeyType_14}}"
  lineKey.15.index="15"
    lineKey.15.category="{{FunctionkeyType_15}}"
  lineKey.16.index="16"
    lineKey.16.category="{{FunctionkeyType_16}}"
    >
    
    
<PHONE>
        <SECURITY
                device.sec.TLS.customCaCert1.set="1"
                device.sec.TLS.customCaCert1=""
                sec.TLS.profileSelection.SIP="ApplicationProfile1"
                device.sec.TLS.customCaCert2.set="1"
/>
</PHONE>



@endswitch
