@if(! is_null($model->id))
    <div class="col-md-9">
        <a href="#" class="btn btn-primary pull-right" onclick="f_editar()">editar</a>
    </div>

    <script>
        function f_editar(){
            $('form *').removeAttr('readonly').removeAttr('disabled');
        }
    </script>
@endif