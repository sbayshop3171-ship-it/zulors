<!DOCTYPE html>
<html lang="en" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/><!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch><o:AllowPNG/></o:OfficeDocumentSettings></xml><![endif]--><!--[if !mso]><!--><!--<![endif]-->
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.3em;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: inherit !important;
        }

        a{
            text-decoration: none;
            color: inherit;
        }

        p {
            line-height: inherit
        }

        sup,
        sub {
            line-height: 0;
            font-size: 75%;
        }

        .email-container {
            overflow: hidden;
            max-width: 380px;
            width: 100%;
            margin: 44px auto;
            background-color: #ffffff;
            border-radius: 8px;
        }
    </style>
</head>
<body style="background-color: #f0f0f0; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tbody>
            <tr>
                <td align="center" >
                    <table class="email-container" cellpadding="0" cellspacing="0" style="background-color: white; overflow: hidden;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <img src="{{ $logotypeUrl }}" alt="Logo" style="display: block; max-height: 26px; margin: 0 auto;">
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f0f0f0; height: 1px;"></td>
                        </tr>
                        <tr>
                            <td style="padding: 20px 20px 40px 20px;">
                                @yield('email_content')
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f0f0f0; height: 1px;"></td>
                        </tr>
                        <tr>
                            <td style="padding: 20px; background-color: #333333;">
                                <div style="margin-bottom: 20px;">
                                    <span style="font-size: 12px; color: #ececec;">
                                        {{ __('email.post_scriptum', ['app_name' => config('app.name')]) }}
                                    </span>
                                </div>

                                <div>
                                    <span style="font-size: 12px; color: #ececec;">
                                        {{ date('Y') }} &copy; {{ config('app.name') }}
                                    </span>
                                    <span style="font-size: 12px; color: #ececec;">
                                        {{ __('labels.all_rights_reserved') }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>