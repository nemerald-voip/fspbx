# How to set up Call Flows

Menu -> Applications > Call Flows

Call flows are used to direct calls to one of two destinations. This is often used for Day Night Mode but can be used to toggle calls between a primary and secondary destination.

-   Name
    -   Day Night
-   Extension
    -   30
-   Feature Code
    -   *30
-   Destination Label
    -   Day Mode
-   Sound
    -   ivr/ivr-day_mode.wav
-   Destination
    -   Often sent to an IVR Menu, Ring Group or any destination.
-   Alternate Label
    -   Night Mode
-   Alternate Sound
    -   ivr/ivr-night_mode.wav
-   Alternate Destination
    -   This could be voicemail, IVR Menu or any destination.
-   Context
    -   Domain name the default will work most of the time.
-   Enable
    -   true
-   Description
    -   A good description can help.

### lua.conf.xml

So for example if I used 30 for the call flow extension and *30 for the call flow Feature Code and I used this in the /etc/freeswitch/autoload_configs/lua.conf.xml file. This needs to be un-commented out in other words remove the <!-- and the --> so that it looks like this and make sure it is saved.

`<param name="startup-script" value="blf_subscribe.lua flow"/>`

Then from command line you can run this command or restart

`fs_cli -x 'luarun blf_subscribe.lua flow'`

Then in the device keys BLF you would have it subscribe to.

`flow+*30`

If you don't want to use the prefix flow+ then you can use the following in the /etc/freeswitch/autoload_configs/lua.conf.xml file instead.

`<param name="startup-script" value="call_flow_monitor.lua"/>`

You start it this way.

`luarun call_flow_monitor.lua`

> ###### Note: Some Grandstream models have *30 and *31 reserved for Call Features. Where ***30** is **Block Caller ID** (for all subsequent calls) and ***31** is **Send Caller ID** (for all subsequent calls). Keep this in mind when selecting your call flow feature code.
>
>