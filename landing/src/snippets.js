export const codeNoAuth = `<span class="kw">final class</span> <span class="cls">MyApiIntegration</span>
{
    <span class="kw">public function</span> <span class="met">__construct</span>(
        <span class="kw">private</span> <span class="cls">IntegrationEngine</span> <span class="var">$engine</span>
    ) {}

    <span class="kw">public function</span> <span class="met">listEmployees</span>(): <span class="cls">GetEmployeesResponse</span>
    {
        <span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(
            <span class="cls">GetEmployeesAction</span><span class="kw">::</span><span class="met">getName</span>()
        );
        \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetEmployeesResponse</span>);
        <span class="kw">return</span> <span class="var">$response</span>;
    }
}`;

export const codePath = `<span class="kw">final class</span> <span class="cls">MyApiIntegration</span>
{
    <span class="kw">public function</span> <span class="met">getEmployee</span>(<span class="kw">int</span> <span class="var">$id</span>): <span class="cls">GetEmployeeResponse</span>
    {
        <span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(
            actionName: <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),
            context: <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),
        );
        \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetEmployeeResponse</span>);
        <span class="kw">return</span> <span class="var">$response</span>;
    }
}`;

export const codeBody = `<span class="kw">final class</span> <span class="cls">MyApiIntegration</span>
{
    <span class="kw">public function</span> <span class="met">createEmployee</span>(
        <span class="kw">string</span> <span class="var">$name</span>,
        <span class="kw">string</span> <span class="var">$correlationId</span>,
    ): <span class="cls">CreateEmployeeResponse</span> {
        <span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(
            actionName: <span class="cls">CreateEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),
            body: <span class="cls">CreateEmployeeBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'name'</span> <span class="kw">=&gt;</span> <span class="var">$name</span>]),
            headers: <span class="kw">new</span> <span class="cls">CorrelationHeaders</span>(<span class="var">$correlationId</span>),
        );
        \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">CreateEmployeeResponse</span>);
        <span class="kw">return</span> <span class="var">$response</span>;
    }
}`;

export const codeGraphQL = `<span class="kw">final class</span> <span class="cls">MyApiIntegration</span>
{
    <span class="kw">public function</span> <span class="met">getUser</span>(<span class="kw">int</span> <span class="var">$id</span>): <span class="cls">GetUserResponse</span>
    {
        <span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(
            actionName: <span class="cls">GetUserAction</span><span class="kw">::</span><span class="met">getName</span>(),
            body: <span class="cls">GetUserBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),
        );
        \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetUserResponse</span>);
        <span class="kw">return</span> <span class="var">$response</span>;
    }
}`;

export const codeSendMany = `<span class="kw">final class</span> <span class="cls">MyApiIntegration</span>
{
    <span class="kw">public function</span> <span class="met">getManyEmployees</span>(<span class="kw">array</span> <span class="var">$ids</span>): <span class="kw">array</span>
    {
        <span class="var">$requests</span> = [];
        <span class="kw">foreach</span> (<span class="var">$ids</span> <span class="kw">as</span> <span class="var">$id</span>) {
            <span class="var">$requests</span>[<span class="var">$id</span>] = <span class="cls">EngineRequest</span><span class="kw">::</span><span class="met">create</span>(
                <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),
                <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),
            );
        }
        <span class="var">$results</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">sendMany</span>(<span class="var">$requests</span>);
        <span class="kw">if</span> (<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">hasFailures</span>()) {
            <span class="kw">throw</span> <span class="met">array_values</span>(<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">errors</span>())[<span class="num">0</span>];
        }
        <span class="kw">return</span> <span class="var">$results</span><span class="kw">-&gt;</span><span class="met">responses</span>();
    }
}`;
