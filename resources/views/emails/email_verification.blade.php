@extends('email_template.master')

@section('content')

<table style="width:100%;max-width:620px;margin:0 auto;background-color:#ffffff;">
    <tbody>
        <tr>
            <td style="padding: 30px 30px 15px 30px;">
                <h2 style="font-size: 18px; color: #6576ff; font-weight: 600; margin: 0;">{{$subject}}</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px 30px 20px">
                <p style="margin-bottom: 10px;">Hi,</p>
                <p style="margin-bottom: 10px;">
                    {!! $content !!}
                    <br>
                </p>
                
                <p style="margin-top: 45px; margin-bottom: 15px;">---- <br> Regards, <br><i>Freebyz Team.</i></p>
            </td>
        </tr>
    </tbody>
</table>

@endsection
